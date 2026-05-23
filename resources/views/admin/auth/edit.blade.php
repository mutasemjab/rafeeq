@extends('layouts.admin')
@section('title')

edit Admin Login
@endsection




@section('contentheaderactive')
تعديل
@endsection


@section('content')

      <div class="card">
        <div class="card-header">
          <h3 class="card-title card_title_center"> edit Admin </h3>
        </div>
        <!-- /.card-header -->
        <div class="card-body">


      <form action="{{ route('admin.login.update',$data['id']) }}" method="post" >
        <div class="row">
        @csrf



        <div class="col-md-6">
<div class="form-group">
  <label>username</label>
  <input name="username" id="name" class="form-control" value="{{ old('username',$data['username']) }}"    >
  @error('username')
  <span class="text-danger">{{ $message }}</span>
  @enderror
</div>
</div>





<div class="col-md-6">
  <div class="form-group">
    <label>   password</label>
    <input name="password" id="email" class="form-control" value=""    >
    @error('password')
    <span class="text-danger">{{ $message }}</span>
    @enderror
  </div>
  </div>







      <div class="col-md-12">
      <div class="form-group text-center">
        <button id="do_add_item_cardd" type="submit" class="btn btn-primary btn-sm"> update</button>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-sm btn-danger">cancel</a>

      </div>
    </div>

  </div>
            </form>



            </div>




        </div>
      </div>






@endsection






