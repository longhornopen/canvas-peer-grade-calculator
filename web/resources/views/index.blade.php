@extends('layouts.master')

@section('content')
    <p>
        Welcome to the Peer Grading Calculator.  Below are listed your currently-open courses.  Choose your course to get started.
    </p>
    <ul>
        @foreach ($courses as $course)
            <li><a href="/course/{{$course->id}}/section_select">{{$course->name}} ({{$course->course_code}})</a></li>
        @endforeach
    </ul>
@endsection
