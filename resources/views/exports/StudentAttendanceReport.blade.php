<table>
    <tr>
        <td>Teacher</td>
        <td>{{$schedules->last()->teacher->name}}</td>
    </tr>
    <tr>
        <td>School Year</td>
        <td>{{$schedules->last()['school_year']}}</td>
    </tr>
    <tr>
        <td>Grade</td>
        <td>{{$schedules->last()['classroom']['grade']}}</td>
    </tr>
    <tr>
        <td>Semester</td>
        <td>{{$schedules->last()['semester']}}</td>
    </tr>
    <tr>
        <td>Subject</td>
        <td>{{$schedules->last()['subject']['name']}}</td>
    </tr>
    <tr>
        <td>Classroom</td>
        <td>{{$schedules->last()['classroom']['name']}}</td>
    </tr>
    <tr>
        <td></td>
    </tr>
</table>
<table border="1">
    <tr>
        <td>date</td>
        <td>student</td>
        <td>status</td>
        <td>notes</td>
    </tr>
    @foreach ($schedules->last()->schedule_attendances as $row)
    <tr>
        <td>{{$row->created_at}}</td>
        <td>{{$row->student->name}}</td>
        <td>{{$row->status}}</td>
        <td>{{$row->notes}}</td>
    </tr>
    @endforeach
</table>