<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use League\Csv\Writer;
use LonghornOpen\CanvasApi\CanvasApiClient;
use function collect;

class PeerGradingController extends Controller
{
    protected function getCanvasApi()
    {
        return new CanvasApiClient(env("CANVAS_URL"), Session::get("oauth2_access_token"));
    }

    public function index()
    {
        $canvasApi = $this->getCanvasApi();
        $teacher_courses = $canvasApi->get('/users/self/courses?enrollment_type=teacher&enrollment_state=active?per_page=100');
        $ta_courses = $canvasApi->get('/users/self/courses?enrollment_type=ta&enrollment_state=active?per_page=100');
        $courses = array_merge($teacher_courses, $ta_courses);
        usort($courses, function ($c1, $c2) {
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

        if (!$rubric_id) {
            return ("ERROR: Can't export if assignment doesn't have a rubric.");
        }

        $student_list = $this->get_student_list($canvasApi, $course_id);
        $peer_reviews = $this->get_peer_reviews($canvasApi, $course_id, $assignment_id);
        $peer_review_scores = $this->get_peer_review_scores_from_rubric($canvasApi, $course_id, $rubric_id);
        $table_data = $this->generate_table_data($student_list, $peer_reviews, $peer_review_scores);
        $csv = Writer::createFromString();
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

    public function gradebookExportScores($course_id, $assignment_id)
    {
        set_time_limit(90);
        $canvasApi = $this->getCanvasApi();
        $info = $this->get_assignment_info($canvasApi, $course_id, $assignment_id);
        $assignment_name = $info['name'];
        $rubric_id = $info['rubric_id'];

        if (!$rubric_id) {
            return ("ERROR: Can't export if assignment doesn't have a rubric.");
        }

        $student_list = $this->get_student_list($canvasApi, $course_id);
        $peer_reviews = $this->get_peer_reviews($canvasApi, $course_id, $assignment_id);
        $peer_review_scores = $this->get_peer_review_scores_from_rubric($canvasApi, $course_id, $rubric_id);
        $table_data = $this->generate_table_data($student_list, $peer_reviews, $peer_review_scores);
        $csv = Writer::createFromString();
        $csv_header = ["Student", "ID", "SIS User ID", $assignment_name . "(" . $assignment_id . ")"];
        $csv->insertOne($csv_header);
        $avg_grades = collect();
        foreach ($table_data as $item) {
            $student_id = $item[5];
            $avg_grade = $item[3];
            $avg_grades[$student_id] = $avg_grade;
        }
        foreach ($student_list as $student_id => $student) {
            if (!$avg_grades->has($student_id)) {
                continue;
            }
            $csv_row = [$student['name'], $student_id, $student['login_id'], $avg_grades[$student_id]];
            $csv->insertOne($csv_row);
        }
        $csv->output('report-' . $assignment_id . '.csv');
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
        $peer_review_scores = $this->get_peer_review_scores_from_rubric($canvasApi, $course_id, $rubric_id);
        $valid_assignments = $this->getAssignments($course_id);

        // peer reviews has not been completed
        if (count($peer_review_scores["score"]) == 0) {
            return view(
                'assignment',
                [
                    'peer_review_completed' => false,
                    'assignments' => $valid_assignments,
                    'assignment_id' => $assignment_id,
                    'assignment_name' => $assignment_name,
                    'class_average' => NULL,
                    'total' => NULL,
                    'table_data' => NULL,
                    'course_id' => $course_id,
                    'canvas_url' => env('CANVAS_URL'),
                    'message' => ''
                ]
            );
        }

        $incomplete_students = [];

        foreach ($peer_reviews as $peer_review) {
            $status = $peer_review['workflow_state'];
            $assessor_student_id = $peer_review['assessor_id'];
            if ($status !== "completed" && in_array($assessor_student_id, $student_list)) {
                $incomplete_students[] = $student_list[$assessor_student_id]['name'];
            }
        }

        $message = null;
        if (count($incomplete_students) > 0) {
            $names = implode(", ", $incomplete_students);
            $message = "The following students have not completed their peer review: " . $names . "!";
        }

        $table_data = $this->generate_table_data($student_list, $peer_reviews, $peer_review_scores);
        $total = $peer_review_scores['total'] ?? 100;
        $table_score_col = 2;
        $class_average = collect($table_data)->pluck($table_score_col)->average();

        return view(
            'assignment',
            [
                'peer_review_completed' => true,
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

    protected function get_assignment_info($canvasApi, $course_id, $assignment_id)
    {
        $assignments = $canvasApi->get('/courses/' . $course_id . '/assignments/' . $assignment_id . '?per_page=200');
        $rubric_id = null;
        if (isset($assignments->rubric_settings)) {
            $rubric_id = $assignments->rubric_settings->id;
        }
        return [
            'name' => $assignments->name,
            'rubric_id' => $rubric_id
        ];
    }

    protected function get_student_list($canvasApi, $course_id)
    {
        $students = $canvasApi->get('/courses/' . $course_id . '/students?per_page=1000');
        $student_list = [];
        foreach ($students as $student) {
            if (!isset($student->login_id)) {
                continue;
            }
            $student_list[$student->id] = [
                "name" => $student->sortable_name,
                "login_id" => $student->login_id
            ];
        }
        return $student_list;
    }

    protected function get_peer_reviews($canvasApi, $course_id, $assignment_id)
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


    protected function get_peer_review_scores_from_rubric($canvasApi, $course_id, $rubric_id)
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

    protected function generate_table_data($student_list, $peer_reviews, $peer_review_scores)
    {
        $student_coll = collect($student_list)->filter(fn($student) => $student['login_id']);
        $peer_review_coll = collect($peer_reviews)->filter(fn($review) => $student_coll->has($review["user_id"]) && $student_coll->has($review["assessor_id"]));

        $table_data = collect();
        $scores_by_student = collect();
        foreach ($peer_review_coll as $peer_review) {
            $student_id = $peer_review["user_id"];

            $key = $peer_review["asset_id"] . $peer_review["assessor_id"];
            if (array_key_exists($key, $peer_review_scores['score'])) {
                // Peer review assignment was scored
                if (!$scores_by_student->has($student_id)) {
                    $scores_by_student[$student_id] = collect();
                }
                $score = $peer_review_scores['score'][$key];
                $scores_by_student[$student_id]->add($score);
            }
        }

        foreach ($peer_review_coll as $peer_review) {
            $student_id = $peer_review["user_id"];
            $student_assessor_id = $peer_review["assessor_id"];
            $student_name = $student_list[$student_id]['name'];
            $assessor_name = $student_list[$peer_review["assessor_id"]]['name'];

            $submission_comments = $peer_review["submission_comments"];

            $submission_comment_list = [];
            if (array_key_exists($student_id, $submission_comments) &&
                array_key_exists($student_assessor_id, $submission_comments[$student_id])) {
                $submission_comment_list = $submission_comments[$student_id][$student_assessor_id];
            }

            $key = $peer_review["asset_id"] . $peer_review["assessor_id"];

            $average = $scores_by_student->has($student_id) ? $scores_by_student[$student_id]->average() : null;
            $score = array_key_exists($key, $peer_review_scores['score']) ? $peer_review_scores['score'][$key] : null;
            $comments = array_key_exists($key, $peer_review_scores['comments']) ? $peer_review_scores['comments'][$key] : [];

            /* Will need for submission comments later */
            // $submission_comments = $peer_review["submission_comments"];
            $table_data->add([
                $student_name,
                $assessor_name,
                $score,
                $average,
                $comments,
                $student_id,
                $submission_comment_list
            ]);
        }
        return $table_data->toArray();
    }

    public function import_grades_to_gradebook(Request $request)
    {
        $canvasApi = $this->getCanvasApi();
        $course_id = $request->get('courseId');
        $assignment_id = $request->get('assignmentId');
        // FIXME this should probably check whether or not things are actually incomplete
        $points_off_incomplete = 0; // floatval($request->get('pointsOffIncomplete'));
        $input_data = json_decode($request->get('peerReviewData'), true);
        $gradebook_data = [];
        $ungraded_students = [];
        foreach ($input_data as $data) {
            $student_name = $data[0];
            $student_id = $data[1];
            $assessor_name = $data[2];
            $grade_assigned = $data[3];
            $grade_average = $data[4];

            if (!array_key_exists($student_id, $gradebook_data) && $grade_assigned != '-') {
                $grade_average = $grade_average - $points_off_incomplete;
                $gradebook_data[$student_id] = $grade_average;
            } else {
                $ungraded_students[$student_id] = $student_name;
            }
        }
        foreach ($gradebook_data as $key => $value) {
            $data = ["submission[posted_grade]" => $value];

            $canvasApi->put(
                '/courses/' . $course_id . '/assignments/' . $assignment_id . '/submissions/' . $key,
                $data
            );
        }

        $response = [
            'status' => 'success',
            'msg' => 'Grades posted successfully.'
        ];
        //Message changes if there are incomplete peer reviews.
        if (count($ungraded_students) > 0) {
            $ungraded_names = implode("; ", $ungraded_students);
            $response['msg'] .= ' However, the following students do not have grades: ' . $ungraded_names;
        }
        return response()->json($response, 200);
    }

    /* This isn't an LTI tool, in the sense that we go through the whole LTI-launch process.
       But teachers sometimes want a link to it in their course for convenience.  LTI is a great
       way of providing that link, and as a side benefit we also get to shortcut the select-a-course
       step (since we can get that course ID from the LTI launch data).
    */
    public function ltiLaunch(Request $request)
    {
        $course_id = $request->get("custom_canvas_course_id");
        return redirect('/course/' . $course_id);
    }

    public function getLTIXML() {
        $app_url = env('APP_URL');
        return response(<<<EOXML
<?xml version="1.0" encoding="UTF-8"?>
<cartridge_basiclti_link xmlns="http://www.imsglobal.org/xsd/imslticc_v1p0"
    xmlns:blti = "http://www.imsglobal.org/xsd/imsbasiclti_v1p0"
    xmlns:lticm ="http://www.imsglobal.org/xsd/imslticm_v1p0"
    xmlns:lticp ="http://www.imsglobal.org/xsd/imslticp_v1p0"
    xmlns:xsi = "http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation = "http://www.imsglobal.org/xsd/imslticc_v1p0 http://www.imsglobal.org/xsd/lti/ltiv1p0/imslticc_v1p0.xsd
    http://www.imsglobal.org/xsd/imsbasiclti_v1p0 http://www.imsglobal.org/xsd/lti/ltiv1p0/imsbasiclti_v1p0.xsd
    http://www.imsglobal.org/xsd/imslticm_v1p0 http://www.imsglobal.org/xsd/lti/ltiv1p0/imslticm_v1p0.xsd
    http://www.imsglobal.org/xsd/imslticp_v1p0 http://www.imsglobal.org/xsd/lti/ltiv1p0/imslticp_v1p0.xsd">
    <blti:title>Canvas Peer Review Grading</blti:title>
    <blti:description>View peer grades and comments, identify outliers, and manage scores.</blti:description>
    <blti:icon>$app_url/images/icon.png</blti:icon>
    <blti:launch_url>$app_url/lti_launch</blti:launch_url>
    <blti:extensions platform="canvas.instructure.com">
      <lticm:property name="tool_id">canvas_peer_review</lticm:property>
      <lticm:property name="privacy_level">public</lticm:property>
      <lticm:options name="course_navigation">
        <lticm:property name="url">$app_url/lti_launch</lticm:property>
        <lticm:property name="text">Canvas Peer Review Grading</lticm:property>
        <lticm:property name="visibility">public</lticm:property>
        <lticm:property name="default">enabled</lticm:property>
        <lticm:property name="enabled">true</lticm:property>
        <lticm:property name="windowTarget">_blank</lticm:property>
      </lticm:options>
    </blti:extensions>
    <cartridge_bundle identifierref="BLTI001_Bundle"/>
    <cartridge_icon identifierref="BLTI001_Icon"/>
</cartridge_basiclti_link>
EOXML)
            ->header('Content-Type', 'text/xml');
    }
}
