<table>
    <tr>
        <td>Title</td>
        <td>{{$assignment->last()->title}}</td>
    </tr>
    <tr>
        <td>Description</td>
        <td>{{$assignment->last()->description}}</td>
    </tr>
    <tr>
        <td>Total Point</td>
        <td>{{$assignment->last()->total_point}}</td>
    </tr>
    <tr>
        <td>School Year</td>
        <td>{{$assignment->last()->school_year}}</td>
    </tr>
    <tr><td></td></tr>
    <tr><td></td></tr>
    <tr><td></td></tr>
    <tr>
        <td>Student</td>
        <td>Score</td>
        <td>Done Remedial</td>
        <td>Score After Remedial</td>
    </tr>
    @foreach($assignment->last()['student_assignments'] as $row)
    <tr>
        <td>{{$row->students['name']}}</td>
        <td>{{$row->score}}</td>
        <td>{{$row->student_assignment_attempts->last()['score'] > $row->score? "TRUE": FALSE}}</td>
        <td>{{$row->student_assignment_attempts->last()['score']}}</td>
    </tr>
    @endforeach
</table>