<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Peer Review</title>

        <link href="https://fonts.googleapis.com/css?family=Roboto" rel="stylesheet">
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>

        <link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" rel="stylesheet">
        <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>

        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/r/bs-3.3.5/jq-2.1.4,dt-1.10.8/datatables.min.css"/>
        <script type="text/javascript" src="https://cdn.datatables.net/1.10.16/js/jquery.dataTables.min.js"></script>

        @yield('header_extras')

        <style>
            body {
                font-family: 'Roboto', sans-serif;
            }

            .header {
                background-color: #a0aec0;
                font-size: 24px;
                padding: 20px;
                margin-bottom: 10px;
            }

            .content_row {
                padding:10px;
            }
        </style>
    </head>
    <body>
    <div class="container-fluid">
        <div class="row"><div class="col-md-12">
            <div class="header">
                Peer Grading Calculator
            </div>
        </div></div>
    @if (!isset($skip_start_over_link))
    <div class="row content_row"><div class="col-md-12">
        <a href="/"><<< Start over from the beginning</a>
    </div></div>
    @endif
        <div class="row content_row"><div class="col-md-12">
        @yield('content')
        </div></div>
    </div>
    </body>
</html>
