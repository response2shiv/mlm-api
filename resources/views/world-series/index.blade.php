@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">World Series Fake Data</div>
                <div class="card-body">

                    @if (Session::has('error'))
                    <div class="alert alert-danger">
                        {{ session('error') }}
                    </div>
                    @endif

                    @if (Session::has('success'))
                    <div class="alert alert-success">
                        {{ session('success') }}
                    </div>
                    @endif

                    @if (Session::has('warning'))
                    <div class="alert alert-warning">
                        {{ session('warning') }}
                    </div>
                    @endif

                    <form method="POST" action="{{ route('worldseries-data') }}">
                        @csrf
                        @method('POST')
                        <div class="form-group">
                            <label for="exampleInputEmail1">Sponsor</label>
                            <input type="text" class="form-control" name="sponsor" id="sponsor"  aria-describedby="emailHelp" value="{{ $sponsor ?? '' }}" placeholder="Enter TSA code">
                        </div>

                        <div class="form-group">
                            <label for="exampleInputPassword1">Username</label>
                            <input type="text" class="form-control" name="username" id="username"  placeholder="Choose an username">
                        </div>

                        <div class="form-group">
                            <label for="staticEmail" class="col-form-label">Password - 1234567</label>
                        </div>

                        <div class="form-group">
                            <label for="exampleFormControlSelect1">Product Package</label>
                            <select class="form-control" name="product">
                                <option value="">Standby Class</option>
                                <option value="2">Coach Class</option>
                                <option value="3">Business Class</option>
                                <option value="4">First Class</option>
                                <option value="5">Upgrade Packages</option>
                            </select>
                        </div>

                        <div class="form-check">
                            <input type="checkbox" id="exampleCheck1" class="form-check-input" name="ticket" value="1">
                            <label class="form-check-label" for="exampleCheck1">Include "VISION 2020 Admission" Ticket?</label><br><br>
                        </div>

                        <button type="submit" name="action" value="fill" class="btn btn-primary inline">Submit</button>
                        <button type="submit" name="action" value="reset" class="btn btn-warning">Clear World Series Data</button>                    
                    </form> 
                    
                  <br /><br />                    

                  @if (isset($overview))
                  <table class="table table-striped">
                      @foreach ($overview as $overview)
                      <tbody>
                          <thead>
                            <tr>
                              <th scope="row" colspan="6" style="background-color: #CCC; color: blue">Sponsor: {{ $overview->sponsor->distid }} ({{ $overview->sponsor->username }})</th>
                          </tr>
                          <tr style="background-color: #E4E4E4">
                              <th scope="">First Base</th>
                              <th scope="">Second Base</th>
                              <th scope="">Third Base</th>
                              <th scope="">Runs</th>
                              <th scope="">Hits</th>
                              <th scope="">Errors</th>
                          </tr>
                      </thead>
                      <tr>
                          <td><a href="#" onclick="fillform('{{ $overview->firstBaseUser->username}}', '{{ $overview->sponsor->distid }}')">
                            {{ $overview->firstBaseUser->username }}</a></td>
                            <td><a href="#" onclick="fillform('{{ $overview->secondBaseUser->username}}', '{{ $overview->sponsor->distid }}')">{{ $overview->secondBaseUser->username }}</a></td>
                            <td><a href="#" onclick="fillform('{{ $overview->thirdBaseUser->username}}', '{{ $overview->sponsor->distid }}')">{{ $overview->thirdBaseUser->username }}</a></td>
                            <td>{{ $overview->runs }}</td>
                            <td>{{ $overview->hits }}</td>
                            <td>{{ $overview->runs }}</td>
                        </tr>
                    </tbody>
                    @endforeach
                </table>
                @endif

            </div>
        </div>
    </div>
</div>
</div>

@endsection

@section('scripts')

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>

<script type="text/javascript">

    function fillform(username, sponsor) {
      $('#username').val(username);
      $('#sponsor').val(sponsor);
  }

</script>
@endsection
