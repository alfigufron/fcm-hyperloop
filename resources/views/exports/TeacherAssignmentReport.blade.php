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
        <td>Minimum Competency</td>
        <td>Remedial</td>
        <td>Status</td>
    </tr>
    @foreach($assignment->last()->student_assignments as $row)
    <tr>
        <td>{{$row->students['name']}}</td>
        <td>{{$row->score}}</td>
        @if($row->students['grade'] >9)
        <td> {{$row->students['schools']->last()['school_minimum_competency']['value']}}</td> 
        @php($minimum_competency=$row->students['schools']->last()['school_minimum_competency']['value'])
        @else
        <td> {{$row->students['schools']->last()['subjects']->last()['minimum_competency']}}</td> 
        @php($minimum_competency=$row->students['schools']->last()['subjects']->last()['minimum_competency']) 
        @endif
        <td>{{$row->score>=$minimum_competency?"FALSE":TRUE}}</td>
        <td>{{$row->status}}</td>
    </tr>
    @endforeach
</table>