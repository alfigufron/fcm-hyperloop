<table>
    <tr>
        <td>Teacher</td>
        <td>{{$teacher}}</td>
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
    <tr>
        <td>date</td>
        <td>basic competency</td>
        <td>main topic</td>
        <td>sub topic</td>
    </tr>
    @foreach ($schedules as $row)
    <tr>
        <td>{{$row->date}}</td>
        <td>{{$row->learning_contract_information->basic_competency}}</td>
        <td>{{$row->learning_contract_information->main_topic}}</td>
        <td>{{$row->learning_contract_information->sub_topic}}</td>
    </tr>
    @endforeach
</table>