@extends('layouts.master')

@section('content')
    <p>
        You've selected the course <i>{{$course->name}} ({{$course->course_code}})</i>, which has multiple sections.  You can calculate grades for:
    </p>
    <form method="post">
        @csrf
        <button type="submit" class="btn btn-default">The entire course</button>
    </form>
    <br>
    <p>
        ...or only the students in one section:
    </p>
    @foreach ($sections as $section)
        <div style="margin-bottom:5px;">
            <form method="post">
                @csrf
                <input type="hidden" name="section_id" value="{{$section->id}}">
                <button type="submit" class="btn btn-default">{{$section->name}}</button>
            </form>
        </div>
    @endforeach
@endsection
