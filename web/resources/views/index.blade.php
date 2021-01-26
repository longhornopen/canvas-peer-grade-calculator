@extends('peerreview::layouts.master')

@section('header_extras')
<link href="https://fonts.googleapis.com/css?family=Roboto" rel="stylesheet">
<link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/r/bs-3.3.5/jq-2.1.4,dt-1.10.8/datatables.min.css"/>

<style>

#main {
    font-family: 'Roboto', sans-serif;
    text-align: center;
}

#assignment {
  text-align: center;
  width:auto !important;
}

h3 {
    color: gray;
    width: 75%;
}

#tool-info-text {
    font-size: 14;
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

#norubric-modal {
    display: none;
    text-align: center;
}
</style>

@endsection

@section('content')
        <div id="loadscreen">
            <div id="loader">
            </div>
        </div>
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
        <br>
        <div id="main">
             
            <h1>Welcome to the Canvas Peer Review Tool!</h1>
            <!-- The peer review table is pulled from the Canvas API  -->
            <p id="tool-info-text">To get more information on how to use the peer review tool, click <a target="_blank" href="https://utexas.instructure.com/courses/633028/pages/how-do-i-use-the-peer-review-grading-tool-in-canvas">here</a>.</p>
            <br>
            <p id="course-id" style="display: none;">{{$course_id}}</p>
            <p id="canvas-url" style="display: none;">{{$canvas_url}}</p>
            <center>
            @if (!empty($assignments))
            <select id="assignment" class="form-control">
            @foreach($assignments as $id=>$assignment)
            <option id="{{$id}}" rubric_status="{{$assignment['has_rubric']}}">{{$assignment['name']}}</option>
            @endforeach
            </select>
            <br>
            <button class="btn btn-primary" id="begin-review">Begin Review</button>
            @else
            <h3>Looks like you don't have any peer review assignments. For more information on how to create a peer review assignment, click <a target="_blank" href="https://community.canvaslms.com/docs/DOC-10094-415254249">here</a>.</h3>
            @endif
            </center>
        </div>        

  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
  <script type="text/javascript" src="https://cdn.datatables.net/1.10.16/js/jquery.dataTables.min.js"></script>
  <script type="text/javascript" src="https://cdn.datatables.net/r/bs-3.3.5/jqc-1.11.3,dt-1.10.8/datatables.min.js"></script>
  <script>

  // v1/scores/1166700/4235927/
  $(document).ready(function(){ 
    
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
      }

    });
    
  });
  </script>

@stop
