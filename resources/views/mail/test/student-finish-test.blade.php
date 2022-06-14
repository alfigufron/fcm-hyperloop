<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EMIAL</title>
</head>
<body>
    <div>{{$data->student->name}} has cleared test {{$data->test->title}}({{$data->test->subject->name}}) at {{date("H:i d F Y",strtotime($data->end_at))}}</div>
</body>
</html>
