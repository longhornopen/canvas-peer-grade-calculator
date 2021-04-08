<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use League\Csv\Writer;
use LonghornOpen\CanvasApi\CanvasApiClient;
use SplTempFileObject;

class PeerGradingController extends Controller
{
    public function index()
    {
        $canvasApi = $this->getCanvasApi();
        $teacher_courses = $canvasApi->get('/users/self/courses?enrollment_type=teacher&enrollment_state=active?per_page=100');
        $ta_courses = $canvasApi->get('/users/self/courses?enrollment_type=ta&enrollment_state=active?per_page=100');
        $courses = array_merge($teacher_courses, $ta_courses);
        usort($courses, function($c1, $c2) {
            return strcasecmp($c2->created_at, $c1->created_at);
        });
        return view(
            'index',
            [
                'courses' => $courses,
                'skip_start_over_link' => true,
            ]
        );
    }

    protected function getCanvasApi()
    {
        return new CanvasApiClient(env("CANVAS_URL"), Session::get("oauth2_access_token"));
    }

    public function ltiLaunch(Request $request)
    {
        $course_id = $request->get("custom_canvas_course_id");
        return redirect()->route('home', ['course_id' => $course_id]);
    }

    protected function getAssignments($course_id)
    {
        $canvasApi = $this->getCanvasApi();
        $assignments = $canvasApi->get('/courses/' . $course_id . '/assignments?per_page=200');
        $valid_assignments = [];
        foreach ($assignments as $assignment) {
            if (!$assignment->published || !$assignment->peer_reviews) {
                continue;
            }
            $valid_assignments[$assignment->id] = [
                'name' => $assignment->name,
                'has_rubric' => isset($assignment->rubric_settings)
            ];
        }
        return $valid_assignments;
    }

    public function courseHome($course_id)
    {
        $valid_assignments = $this->getAssignments($course_id);
        return view(
            'course_home',
            [
                'canvas_url' => env('CANVAS_URL'),
                'course_id' => $course_id,
                'assignments' => $valid_assignments
            ]
        );
    }

    public function exportScores($course_id, $assignment_id)
    {
        set_time_limit(90);
        $canvasApi = $this->getCanvasApi();
        $info = $this->get_assignment_info($canvasApi, $course_id, $assignment_id);
        $rubric_id = $info['rubric_id'];
        if ($rubric_id) {
            $student_list = $this->get_student_list($canvasApi, $course_id);
            $peer_reviews = $this->get_peer_reviews($canvasApi, $course_id, $assignment_id);
            $peer_review_scores = $this->get_peer_review_scores_from_rubric($canvasApi, $course_id, $rubric_id);
            $data = $this->generate_table_data($student_list, $peer_reviews, $peer_review_scores);
            $table_data = $data['table_data'];
            $csv = Writer::createFromFileObject(new SplTempFileObject());
            $csv_header = ["Student Name", "Grader Name", "Grade Assigned", "Grade Average", "Submission Comments"];
            if (count($table_data) !== 0) {
                $num_criterias = count($table_data[0][4]);
                foreach (range(1, $num_criterias) as $cid) {
                    $csv_header[] = "Comments for Criteria #" . $cid;
                }
            }
            $csv->insertOne($csv_header);
            foreach ($table_data as $row_entry) {
                $csv_row = [$row_entry[0], $row_entry[1], $row_entry[2], $row_entry[3], implode(", ", $row_entry[6])];
                foreach ($row_entry[4] as $criteria_comment) {
                    $csv_row[] = $criteria_comment;
                }
                $csv->insertOne($csv_row);
            }
            $csv->output('report-' . $assignment_id . '.csv');
        }
    }

    public function gradebookExportScores($course_id, $assignment_id)
    {
        set_time_limit(90);
        $canvasApi = $this->getCanvasApi();
        $info = $this->get_assignment_info($canvasApi, $course_id, $assignment_id);
        $assignment_name = $info['name'];
        $rubric_id = $info['rubric_id'];
        if ($rubric_id) {
            $student_list = $this->get_student_list($canvasApi, $course_id);
            $peer_reviews = $this->get_peer_reviews($canvasApi, $course_id, $assignment_id);
            $peer_review_scores = $this->get_peer_review_scores_from_rubric($canvasApi, $course_id, $rubric_id);
            $data = $this->generate_table_data($student_list, $peer_reviews, $peer_review_scores, true);
            $table_data = $data['table_data'];
            $csv = Writer::createFromFileObject(new SplTempFileObject());
            $csv_header = ["Student", "ID", "SIS User ID", $assignment_name . "(" . $assignment_id . ")"];
            $csv->insertOne($csv_header);
            foreach ($table_data as $row_entry) {
                $csv_row = [$row_entry[0], $row_entry[1], $row_entry[2], $row_entry[3]];
                $csv->insertOne($csv_row);
            }
            $csv->output('report-' . $assignment_id . '.csv');
        }
    }

    /**
     * Retrieve scores for students in course
     */
    public function assignmentHome($course_id, $assignment_id)
    {
        /**
         * Call Canvas API to get data on peer review assignment scores
         *
         */
        $canvasApi = $this->getCanvasApi();
        $info = $this->get_assignment_info($canvasApi, $course_id, $assignment_id);
        $assignment_name = $info['name'];
        $rubric_id = $info['rubric_id'];
        $student_list = $this->get_student_list($canvasApi, $course_id);
        $peer_reviews = $this->get_peer_reviews($canvasApi, $course_id, $assignment_id);
        $table_data = null;
        $peer_review_scores = $this->get_peer_review_scores_from_rubric($canvasApi, $course_id, $rubric_id);
        $data = $this->generate_table_data($student_list, $peer_reviews, $peer_review_scores);

        $incomplete_students = [];

        foreach ($peer_reviews as $value) {
            $status = $value['workflow_state'];
            $id = $value['assessor_id'];
            if ($status !== "completed" && in_array($id, $student_list)) {
                $incomplete_students[] = $student_list[$id]['name'];
            }
        }

        $message = null;
        if (count($incomplete_students) > 0) {
            $names = implode(", ", $incomplete_students);
            $message = "The following students have not completed their peer review: " . $names . "!";
        }

        $table_data = $data['table_data'];
        $total = $data['total'];
        $class_average = $this->get_class_average($table_data);
        $valid_assignments = $this->getAssignments($course_id);
        return view(
            'assignment',
            [
                'assignments' => $valid_assignments,
                'assignment_id' => $assignment_id,
                'assignment_name' => $assignment_name,
                'class_average' => $class_average,
                'total' => $total,
                'table_data' => $table_data,
                'course_id' => $course_id,
                'canvas_url' => env('CANVAS_URL'),
                'message' => $message
            ]
        );
    }

    /**
     * Helper functions for loadCourseInfo
     */
    public function get_assignment_info($canvasApi, $course_id, $assignment_id)
    {
        $data = $canvasApi->get('/courses/' . $course_id . '/assignments/' . $assignment_id . '?per_page=200');
        $rubric_id = null;
        if (isset($data->rubric_settings)) {
            $rubric_id = $data->rubric_settings->id;
        }
        $info = [];
        $info['name'] = $data->name;
        $info['rubric_id'] = $rubric_id;
        return $info;
    }

    public function get_student_list($canvasApi, $course_id)
    {
        $data = $canvasApi->get('/courses/' . $course_id . '/students?per_page=1000');
        $student_list = [];
        foreach ($data as $student) {
            if (!isset($student->login_id)) {
                continue;
            }
            $canvas_id = $student->id;
            $student_list[$canvas_id] = [];
            $student_list[$canvas_id]["name"] = $student->sortable_name;
            $student_list[$canvas_id]["login_id"] = $student->login_id;
        }
        return $student_list;
    }

    public function get_peer_reviews($canvasApi, $course_id, $assignment_id)
    {
        ini_set('memory_limit', '1024M');
        $data = $canvasApi->get(
            '/courses/' . $course_id . '/assignments/' . $assignment_id . '/peer_reviews?per_page=1000'
        );
        $submissions = $canvasApi->get(
            '/courses/' . $course_id . '/assignments/' . $assignment_id . '/submissions?include=submission_comments&per_page=1000'
        );
        $peer_reviews = [];
        $submission_scores = [];
        $submission_comments = [];
        foreach ($submissions as $submission) {
            $submission_scores[$submission->id] = $submission->score;
            foreach ($submission->submission_comments as $submission_comment) {
                $author_id = $submission_comment->author_id;
                if (!array_key_exists($submission->user_id, $submission_comments)) {
                    $submission_comments[$submission->user_id] = [];
                }
                if (!array_key_exists($author_id, $submission_comments[$submission->user_id])) {
                    $submission_comments[$submission->user_id][$author_id] = [];
                }
                $submission_comments[$submission->user_id][$author_id][] = $submission_comment->comment;
            }
        }

        foreach ($data as $peer_review) {
            $id = $peer_review->id;
            $user_id = $peer_review->user_id;
            $asset_id = $peer_review->asset_id;
            $workflow_state = $peer_review->workflow_state;
            $assessor_id = $peer_review->assessor_id;
            $peer_reviews[$id] = [];
            $peer_reviews[$id]['user_id'] = $user_id;
            $peer_reviews[$id]['asset_id'] = $asset_id;
            $peer_reviews[$id]['workflow_state'] = $workflow_state;
            $peer_reviews[$id]['assessor_id'] = $assessor_id;
            $peer_reviews[$id]['submission_comments'] = $submission_comments;
            try {
                $submission_score = $submission_scores[$user_id];
                $peer_reviews[$id]['score'] = $submission_score;
            } catch (\Exception $e) {
                continue;
            }
        }
        return $peer_reviews;
    }

    public function get_peer_review_scores_from_rubric($canvasApi, $course_id, $rubric_id)
    {
        $data = $canvasApi->get(
            '/courses/' . $course_id . '/rubrics/' . $rubric_id . '?include=peer_assessments&style=full&per_page=1000'
        );

        $peer_review_scores = [];
        if ($data !== null) {
            $assessments = $data->assessments;
            $peer_review_scores['score'] = [];
            foreach ($assessments as $assessment) {
                $asset_id = $assessment->artifact_id;
                $assessor_id = $assessment->assessor_id;
                $assess_id = substr($assessor_id, 0, 6);
                $key = $asset_id . $assessor_id;
                $score = $assessment->score;
                $comments = [];
                $comment_data = $assessment->data;
                foreach ($comment_data as $comment) {
                    if ($comment->comments != "") {
                        $comments[] = $comment->comments;
                    }
                }
                $peer_review_scores['score'][$key] = $score;
                $peer_review_scores['total'] = $data->points_possible;
                $peer_review_scores['comments'][$key] = $comments;
            }
        }
        return $peer_review_scores;
    }

    public function generate_table_data($student_list, $peer_reviews, $peer_review_scores)
    {
        $table_data = [];
        if (!isset($peer_review_scores['total']) || $peer_review_scores['total'] == null) {
            $total = 100;
        } else {
            $total = $peer_review_scores['total'];
        }
        $averages = [];
        $counts = [];
        foreach ($peer_reviews as $key => $values) {
            if (array_key_exists($values["user_id"], $student_list) &&
                array_key_exists($values["assessor_id"], $student_list)) {
                $student_login_id = $student_list[$values["user_id"]]['login_id'];
                $assessor_login_id = $student_list[$values["assessor_id"]]['login_id'];
                if ($student_login_id !== '' && $assessor_login_id !== '') {
                    $key = $values["asset_id"] . $values["assessor_id"];
                    $score = null;
                    if (array_key_exists($key, $peer_review_scores['score'])) {
                        // Peer review assignment was scored
                        $score = $peer_review_scores['score'][$key];
                    }

                    if ($score !== null) {
                        /* Store average */
                        if (array_key_exists($student_login_id, $averages)) {
                            $averages[$student_login_id] += $score;
                            $counts[$student_login_id] += 1;
                        } else {
                            $averages[$student_login_id] = $score;
                            $counts[$student_login_id] = 1;
                        }
                    }
                }
            }
        }

        foreach ($peer_reviews as $key => $values) {
            if (array_key_exists($values["user_id"], $student_list) &&
                array_key_exists($values["assessor_id"], $student_list)) {
                $student_login_id = $student_list[$values["user_id"]]['login_id'];
                $student_name = $student_list[$values["user_id"]]['name'];
                $assessor_login_id = $student_list[$values["assessor_id"]]['login_id'];
                $assessor_name = $student_list[$values["assessor_id"]]['name'];

                $submission_comments = $values["submission_comments"];

                if ($student_login_id != '' && $assessor_login_id != '') {
                    $submission_comment_list = [];
                    if (array_key_exists($values["user_id"], $submission_comments) &&
                        array_key_exists($values["assessor_id"], $submission_comments[$values["user_id"]])) {
                        $submission_comment_list = $submission_comments[$values["user_id"]][$values["assessor_id"]];
                    }

                    $key = $values["asset_id"] . $values["assessor_id"];

                    $score = null;
                    /* Retrieve average */
                    $average = null;
                    if (array_key_exists($student_login_id, $averages)) {
                        $average = $averages[$student_login_id] / $counts[$student_login_id];
                    }
                    $comments = [];
                    if (array_key_exists($key, $peer_review_scores['score'])) {
                        // Peer review assignment was scored
                        $score = $peer_review_scores['score'][$key];
                    }
                    if (array_key_exists($key, $peer_review_scores['comments'])) {
                        $comments = $peer_review_scores['comments'][$key];
                    }
                    /* Will need for submission comments later */
                    // $submission_comments = $values["submission_comments"];
                    $curr_data = [
                        $student_name,
                        $assessor_name,
                        $score,
                        $average,
                        $comments,
                        $values["user_id"],
                        $submission_comment_list
                    ];
                    $table_data[] = $curr_data;
                }
            }
        }
        return ['table_data' => $table_data, 'total' => $total];
    }

    public function get_class_average($table_data)
    {
        $class_average = 0;
        $count = 0;
        foreach ($table_data as $data) {
            $score = $data[2];
            if ($score !== null) {
                $class_average += $score;
                ++$count;
            }
        }
        if ($count === 0) {
            return 0;
        }
        return $class_average / $count;
    }

    public function import_grades_to_gradebook(Request $request)
    {
        $canvasApi = $this->getCanvasApi();
        $request_data = $request->all();
        $course_id = $request_data['courseId'];
        $assignment_id = $request_data['assignmentId'];
        // FIXME this should probably check whether or not things are actually incomplete
        $points_off_incomplete = 0; // floatval($request_data['pointsOffIncomplete']);
        $input_data = json_decode($request_data['peerReviewData'], true);
        $gradebook_data = [];
        $complete_names = [];
        $graded_names = [];
        foreach ($input_data as $data) {
            $student_name = $data[0];
            $student_id = $data[1];
            $assessor_name = $data[2];
            $grade_assigned = $data[3];
            $grade_average = $data[4];
            $complete_names[] = $student_name;

            if (!array_key_exists($student_id, $gradebook_data) && $grade_assigned != '-') {
                $graded_names[] = $student_name;
                $grade_average = $grade_average - $points_off_incomplete;
                $gradebook_data[$student_id] = $grade_average;
            }
        }
        foreach ($gradebook_data as $key => $value) {
            $data = ["submission[posted_grade]" => $value];

            $canvasApi->put(
                '/courses/' . $course_id . '/assignments/' . $assignment_id . '/submissions/' . $key,
                $data
            );
        }

        $names1 = array_diff($complete_names, $graded_names);
        $names = array_values($names1);
        $final_names = implode(", ", $names);

        //Message changes if there are incomplete peer reviews.
        if (count($input_data) !== count($gradebook_data)) {
            $response = [
                'status' => 'success',
                'msg' => 'Grades posted successfully. However, the following students do not have grades: ' . $final_names . "!"
            ];
        } elseif (count($input_data) !== count($gradebook_data)) {
            $response = [
                'status' => 'success',
                'msg' => 'Grades posted successfully.'
            ];
        }
        return response()->json($response, 200);
    }
}
