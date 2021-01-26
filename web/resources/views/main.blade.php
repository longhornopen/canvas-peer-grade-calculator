@extends('peerreview::layouts.master')

@section('header_extras')
<link href="https://fonts.googleapis.com/css?family=Roboto" rel="stylesheet">
<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.0.0/jquery.min.js"></script>
<link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" rel="stylesheet">
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/r/bs-3.3.5/jq-2.1.4,dt-1.10.8/datatables.min.css"/>

<style>
    #main {
        font-family: 'Roboto', sans-serif;
        text-align: center;
    }

    #nav {
        margin-top: 15px;
    }

    #switch-assignmens {
        text-align: left;
    }

    #peer-review-data_filter {
        text-align:center;
    }

    #peer-review-data_length {
        text-align:left;
        margin-left: 10px;
    }

    #peer-review-data_wrapper {
        text-align: center;
        margin: auto;
        width: 95%;
    }

    #import-message {
        /*background-color: red;*/
        position: absolute;
        width: 100%;
        display: none;
        text-align: center;
        z-index: 999999;
    }

    #export-message {
        position: absolute;
        width: 100%;
        display: none;
        text-align: center;
        z-index: 999999;
    }

    #show-outliers, #toggle-comments {
        z-index: 1;
    }

    .comment-togglers, #comment-header {display: none;}

    .student-eid, #student-eid-header {
        display: none;
    }

    h1 {
        font-size: 18pt;
    }
    #class-average {
        font-size: 14pt;
        font-weight: bold;
    }

    .disp-right {
        position: absolute;
        top: 0;
        right: 15px;
    }

    .disp-left {
        position: absolute;
        top: 0;
        left: 15px;
    }

    #outlier-modal {
        text-align: center;
    }

    .linked-data {
        text-decoration: underline;
        cursor: pointer;
    }

    /* Center the loader */
    #loader {
    position: absolute;
    left: 50%;
    top: 50%;
    z-index: 1;
    width: 150px;
    height: 150px;
    margin: -75px 0 0 -75px;
    border: 16px solid #f3f3f3;
    border-radius: 50%;
    border-top: 16px solid #3498db;
    width: 120px;
    height: 120px;
    -webkit-animation: spin 2s linear infinite;
    animation: spin 2s linear infinite;
    }

    @-webkit-keyframes spin {
    0% { -webkit-transform: rotate(0deg); }
    100% { -webkit-transform: rotate(360deg); }
    }

    @keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
    }

    /* Add animation to "page content" */
    .animate-bottom {
    position: relative;
    -webkit-animation-name: animatebottom;
    -webkit-animation-duration: 1s;
    animation-name: animatebottom;
    animation-duration: 1s
    }

    @-webkit-keyframes animatebottom {
    from { bottom:-100px; opacity:0 }
    to { bottom:0px; opacity:1 }
    }

    @keyframes animatebottom {
    from{ bottom:-100px; opacity:0 }
    to{ bottom:0; opacity:1 }
    }

    #loadscreen {
        display: none;
        text-align: center;
    }

</style>
@endsection

@section('content')
        <div class="modal fade" id="norubric-modal" role="dialog">
            <div class="modal-dialog">

                <!-- Modal content-->
                <div class="modal-content">
                    <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title">No Attached Rubric</h4>
                    </div>
                    <div class="modal-body">
                        <p>
                        This peer review assignment does not have a rubric associated with it. 
                        To get the full functionality of the peer review tool, please click 
                        <a target="_blank" href="https://community.canvaslms.com/docs/DOC-12861-4152724129">here</a> 
                        to get more information on how to attach a rubric to the assignment.
                        If you only wish to view the comments for each submission, 
                        please visit the <a id="speedgrader-link" target="_blank" href="">speedgrader</a>.
                        </p>
                    </div>
                    <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                    </div>
                </div>

            </div>
        </div>
        <div id="import-message" class="alert alert-warning alert-dismissible" role="alert">
            <button type="button" class="close" aria-hidden="true">&times;</button>
        </div>
        <div id="export-message" class="alert alert-success" role="alert"></div>
        <div id="loadscreen">
            <div id="loader">
            </div>
        </div>
        <div id="main">
            <br>
            <h1><a target="_blank" href="{{$canvas_url}}/courses/{{$course_id}}/assignments/{{$assignment_id}}">{{$assignment_name}}</a></h1>
            <br>
            <br>
            <p id="course-id" style="display: none;">{{$course_id}}</p>
            <p id="assignment-id" style="display: none;">{{$assignment_id}}</p>
            <div id="switch-assignments" class="disp-left">
                @if (!empty($assignments))
                <select id="assignment" class="form-control" style="margin-bottom: 5px;">
                <option id="" selected disabled hidden>Switch Assignments</option>
                @foreach($assignments as $id=>$assignment)
                <option id="{{$id}}" rubric_status="{{$assignment['has_rubric']}}">{{$assignment['name']}}</option>
                @endforeach
                <br>
                </select>
                <button class="btn btn-primary" id="begin-review">Begin Review</button>
                @else
                <h3>Looks like you don't have any peer review assignments. For more information on how to create a peer review assignment, click <a target="_blank" href="https://community.canvaslms.com/docs/DOC-10094-415254249">here</a>.</h3>
                @endif
             </div>
            <!-- The peer review table is pulled from the Canvas API  -->
            @if ($table_data)

             <div class="btn-group" role="group" aria-label="Filter Buttons">
                <button id="toggle-comments" type="button" class="btn btn-primary">Show Comments</button>
                <button type="button" class="btn btn-primary" id="show-outliers">Identify Outliers</button>
             </div>

             <div id="disp-average" class="disp-right">
                <button id="import-scores" class="btn btn-primary">Import Scores to Gradebook</button>
                <button id="export-grades" class="btn btn-primary">Export Scores to CSV</button>
                <h1>Class Average:</h1>
                <span id="class-average">{{round(ceil($class_average*100)/100,2)}} / {{$total}}</span>
             </div>

             <table id="peer-review-data" class="table table-striped table-bordered">
                <thead>
                <tr id="peer-review-data-header">
                    <th>Student Name</th>
                    <th id="student-eid-header">Student EID</th>
                    <th>Grader Name</th>
                    <th>Grade Assigned</th>
                    <th>Grade Average</th>
                    <th id="comment-header">Comments</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($table_data as $key=>$data)
                <tr>
                    <td class="linked-data" onclick='window.open("{{$canvas_url}}/courses/{{$course_id}}/gradebook/speed_grader?assignment_id={{$assignment_id}}#%7B\"student_id\"%3A\"{{$data[5]}}\"%7D", "_blank").focus();'>
                        {{$data[0]}}
                    </td>
                    <td class="student-eid">{{$data[5]}}</td>
                    <td>{{$data[1]}}</td>
                    @if ($data[2] !== null)
                    <td>{{$data[2]}}</td>
                    @else
                    <td>-</td>
                    @endif
                    @if ($data[3] !== null)
                    <td>{{round(ceil($data[3]*100)/100,2)}}</td>
                    @else
                    <td>-</td>
                    @endif
                    @if ($data[4] || !empty($data[4]) || $data[6])
                    <td class="comment-togglers"><a data-toggle="modal" data-target="#comment-modal-{{$key}}" href="">View Comments</a></td>
                    @else
                    <td class="comment-togglers">No Comments</td>
                    @endif
                    <!-- Modal -->
                    <div class="modal fade" id="comment-modal-{{$key}}" role="dialog">
                        <div class="modal-dialog">

                        <!-- Modal content-->
                        <div class="modal-content">
                            <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                            <h4 class="modal-title">Comments</h4>
                            </div>
                            <div class="modal-body">
                                @foreach($data[4] as $cid=>$comment)
                                <p>
                                    <b>Comments for Criteria #{{$cid + 1}}:</b>
                                </p>
                                <p>
                                    {{$comment}}
                                </p>
                                @endforeach
                                @foreach($data[6] as $cid=>$comment)
                                <p>
                                    <b>Submission Comment #{{$cid + 1}}:</b>
                                </p>
                                <p>
                                    {{$comment}}
                                </p>
                                @endforeach
                            </div>
                            <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                            </div>
                        </div>

                        </div>
                    </div>
                </tr>
                @endforeach
                </tbody>
             </table>
             @else
                <p>No peer reviews have been completed.</p>
             @endif

        </div>

        <div class="modal fade" id="outlier-modal" role="dialog">
            <div class="modal-dialog">

                <!-- Modal content-->
                <div class="modal-content">
                    <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title">Outliers</h4>
                    </div>
                    <div class="modal-body">
                        <p>
                        To find outliers within student grades, please input a numeric value that represents the point deviation that would be considered an outlier. The grades given to a student that deviate by the provided amount from the average of all grades given to the student will be displayed in the table.
                        </p>
                        <input id="stddev" class="form-control" type="text" placeholder="Point Deviation"/>
                        <br>
                        <button id="find-outlier" class="btn btn-primary">Find Outliers</button>
                    </div>
                    <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                    </div>
                </div>

            </div>
        </div>

        <div class="modal fade" id="import-grades-modal" role="dialog">
            <div class="modal-dialog">

                <!-- Modal content-->
                <div class="modal-content">
                    <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title">Importing Grades to Gradebook</h4>
                    </div>
                    <div class="modal-body">
                        <p>
                        ** THIS ACTION CANNOT BE UNDONE **
                        </p>
                        <!-- <input type="checkbox" name="mute-assignment" id="mute-assignment" value="muted">&nbsp;Mute assignment<br>
                        <br> -->
                        <!--
                        <label for="points-off-incomplete">Point deductions for incomplete peer reviews:</label>
                        <input type="number" name="points-off-incomplete" id="points-off-incomplete" value="0"/>&nbsp; points
                        -->
                        <button id="import-grades" class="btn btn-primary">Import to Gradebook</button>
                    </div>
                    <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="export-grades-modal" role="dialog">
            <div class="modal-dialog">

                <!-- Modal content-->
                <div class="modal-content">
                    <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title">Export Grades as a CSV</h4>
                    </div>
                    <div class="modal-body">
                        <!-- <input type="checkbox" name="mute-assignment" id="mute-assignment" value="muted">&nbsp;Mute assignment<br>
                        <br> -->
                        <p>
                        The "Grades Only" option will be in the correct format to upload into the Canvas gradebook. Use this option if you want to manually change grades, and upload them back to Canvas. The "Grades + Comments" option will not be in the correct format to upload back into the Canvas gradebook.
                        </p>
                        <button id="export-scores1" class="btn btn-primary">Grades Only</button>
                        <button id="export-scores" class="btn btn-primary">Grades + Comments</button>
                    </div>
                    <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/1.10.16/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/r/bs-3.3.5/jqc-1.11.3,dt-1.10.8/datatables.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery-confirm/3.3.2/jquery-confirm.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-confirm/3.3.2/jquery-confirm.min.js"></script>

    <script>
        @if ($message)
            // alert("{{$message}}")
            $.alert({
                title: 'Missing Reviews!',
                content: "{{$message}}",
            });
        @endif

$(document).ready(function(){

            jQuery.fn.dataTableExt.oSort['full_name-asc'] = function(x,y) {
                var last_name_x = x.split(" ")[1];
                var last_name_y = y.split(" ")[1];
                return ((last_name_x < last_name_y) ? -1 : ((last_name_x > last_name_y) ? 1 : 0));
            };

            jQuery.fn.dataTableExt.oSort['full_name-desc'] = function(x,y) {
                var last_name_x = x.split(" ")[1];
                var last_name_y = y.split(" ")[1];
                return ((last_name_x < last_name_y) ? 1 : ((last_name_x > last_name_y) ? -1 : 0));
            };

            var dataTable = $('#peer-review-data').DataTable({
                stateSave: true,
                aoColumnDefs: [
                    {"sType": "full_name", "aTargets": [0, 1]}
                ]
            });

            $('.dataTables_filter').parent()[0].classList.remove("col-sm-6");
            $('.dataTables_filter').parent()[0].classList.add("col-sm-12");

            $('#toggle-comments').click(showComments);

            $(window).resize(function() {
                $winSize = $(window).width();

                if ($winSize <= 1100) {
                    $('#disp-average').removeClass('disp-right');
                } else {
                    if (!$('#disp-average').hasClass('disp-right'))
                        $('#disp-average').addClass('disp-right');
                }

            });

            var filtered = 0;
            $('#show-outliers').click(function() {
                if (!filtered) {
                    $("#outlier-modal").modal('toggle');
                }
                else {
                    $("#loadscreen").fadeIn(function(){
                        $("#main").fadeOut();
                    });
                    window.location = window.location.href;
                }

            });

            $('#find-outlier').click(function() {

               if (isNaN($('#stddev').val())) {

                   alert("Please enter a valid number");

               } else {

                   var stdDev = parseFloat($('#stddev').val());

                   var removedData = dataTable
                    .rows( function ( idx, data, node ) {
                        if (!isNaN(data[3]) && !isNaN(data[4]))
                            return Math.abs(data[3] - data[4]) < stdDev;
                        else
                            return true;
                    } )
                    .remove()
                    .draw();

                    $("#show-outliers").html("Reset Outliers");

                    filtered = true;
               }

            });

            $('#begin-review').click(function() {

              var assignment_name = $("#assignment").val();
              var id = $('#assignment').find(":selected").attr("id");
              var has_rubric = $('#' + id).attr("rubric_status");
              if (has_rubric === "1") {
                $("#loadscreen").fadeIn(function(){
                  $("#main").fadeOut();
                });
                window.location.href= "/peerreview/v1/scores/" + $('#course-id').text() + "/" + id;
              } else {
                var canvas_url = $("#canvas-url").text();
                var course_id = $("#course-id").text();
                $("#speedgrader-link").attr("href", canvas_url + "/courses/" + course_id + "/gradebook/speed_grader?assignment_id=" + id);
                $("#norubric-modal").modal('toggle');
                $('#assignment').val("Switch Assignments").prop('selected', true);

              }

            });

            $('#assignment-switch').click(function() {

                $("#loadscreen").fadeIn(function(){
                    $("#main").fadeOut();
                });

                window.location.href = '/peerreview/home/' + $('#course-id').text();

            });

            $('#import-scores').click(function() {
                $('#import-grades-modal').modal('toggle');
            });

            $('#export-grades').click(function() {
                $('#export-grades-modal').modal('toggle');
            });

            $('#export-scores').click(function() {

                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    }
                });

                $("#loadscreen").fadeIn(function(){
                    $("#main").fadeOut();
                });

                $.ajax({
                    type: 'GET',
                    url: '/peerreview/v1/export/scores/' + $('#course-id').text() + '/' + $('#assignment-id').text(),
                    success: function(data) {
                        $('#export-grades-modal').modal('toggle');
                        var a = window.document.createElement("a");
                        const blob = new Blob([data], {type: "text/csv"}),
                            url = window.URL.createObjectURL(blob);
                        a.href = url;
                        a.download = "report_" + $('#assignment-id').text() + ".csv";
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        window.URL.revokeObjectURL(url);
                        $("#main").fadeIn(function(){
                            $("#loadscreen").fadeOut();
                        });
                        $('#export-message').html("Export successful!");
                        $("#export-message").fadeTo(2000, 500).slideUp(500, function(){
                            $("#export-message").slideUp(500);
                        });
                        console.log("OK");
                    },
                    error: function(data) {
                        console.log("Error: ", data);
                        $("#main").fadeIn(function(){
                            $("#loadscreen").fadeOut();
                        });
                    }
                });

                // document.location.href = '/peerreview/v1/export/scores/' + $('#course-id').text() + '/' + $('#assignment-id').text();

            });

            $('#export-scores1').click(function() {

                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    }
                });

                $("#loadscreen").fadeIn(function(){
                    $("#main").fadeOut();
                });

                $.ajax({
                    type: 'GET',
                    url: '/peerreview/v1/export1/scores/' + $('#course-id').text() + '/' + $('#assignment-id').text(),
                    success: function(data) {
                        $('#export-grades-modal').modal('toggle');
                        var a = window.document.createElement("a");
                        const blob = new Blob([data], {type: "text/csv"}),
                            url = window.URL.createObjectURL(blob);
                        a.href = url;
                        a.download = "report_" + $('#assignment-id').text() + ".csv";
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        window.URL.revokeObjectURL(url);
                        $("#main").fadeIn(function(){
                            $("#loadscreen").fadeOut();
                        });
                        $('#export-message').html("Export successful!");
                        $("#export-message").fadeTo(2000, 500).slideUp(500, function(){
                            $("#export-message").slideUp(500);
                        });
                        console.log("OK");
                    },
                    error: function(data) {
                        console.log("Error: ", data);
                        $("#main").fadeIn(function(){
                            $("#loadscreen").fadeOut();
                        });
                    }
                });

                // document.location.href = '/peerreview/v1/export/scores/' + $('#course-id').text() + '/' + $('#assignment-id').text();

            });

            $('#import-grades').click(function() {

                $("#loadscreen").fadeIn(function(){
                    $("#main").fadeOut();
                });

                var tableData = dataTable.rows().data();

                var peerReviewData = [];
                tableData.each(function(value, index) {

                    peerReviewData.push(value);

                });

                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    }
                });

                $.ajax({

                    type: 'POST',
                    url: '/peerreview/v1/import/scores',
                    data: {'peerReviewData': JSON.stringify(peerReviewData),
                           'courseId': $('#course-id').text(),
                           'assignmentId': $('#assignment-id').text(),
                           'assignmentMuted': $('#mute-assignment').is(":checked"),
                           'pointsOffIncomplete': $('#points-off-incomplete').val()
                          },
                    success: function(data) {
                        if (data.status == 'success') {
                            $('#import-grades-modal').modal('toggle');
                            $("#main").fadeIn(function(){
                                $("#loadscreen").fadeOut();
                            });
                            var alert1 = $('#import-message').html(data.msg);
                            alert1.show()
                            alert1.on('click', function() {
                                $(this).alert('close'); 
                            });
                            //$("#import-message").fadeTo(2000, 100);
                            //.slideUp(100, function(){
                            //     $("#import-message").slideUp(50);
                            // })
                            console.log(data.msg);
                        }
                    },
                    error: function(data) {
                        console.log("Error: ", data);
                        $('#import-grades-modal').modal('toggle');
                        $("#main").fadeIn(function(){
                            $("#loadscreen").fadeOut();
                        });
                    }

                });

            });

});

    function showComments() {
        /** Generate modal for comment */
        if ($("#comment-header").css("display") === "none") {
            $('#comment-header').show();
            $('.comment-togglers').show();
            $('#toggle-comments').html("Hide Comments");
        }
        else {
            $('#comment-header').hide();
            $('.comment-togglers').hide();
            $('#toggle-comments').html("Show Comments");
        }
    }

    </script>
@stop
