<table>
    <tr>
        <td>Name</td>
        <td>{{$attendance->last()->teacher->name}}</td>
    </tr>
    <tr>
        <td>NIP</td>
        <td>{{$attendance->last()->teacher->nip}}</td>
    </tr>
    <tr rowspan="5" colspan="5"><td></td></tr>
    <tr>
        <td>date</td>
        <td>Clasroom</td>
        <td>Subject</td>
        <td>status</td>
        <td>notes</td>
    </tr>
    @foreach($attendance as $row)
    <tr>
        <td>{{$row->schedule->date}}</td>
        <td>{{$row->schedule->classroom['name']}}</td>
        <td>{{$row->schedule->subject['name']}}</td>
        <td>{{$row->status}}</td>
        <td>{{$row->notes}}</td>
    </tr>
    @endforeach
</table>