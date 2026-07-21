@extends('admin.layouts.header') 
@section('content')
  <div class="container">
      <div class="flex justify-center authentication authentication-basic items-center h-full text-defaultsize text-defaulttextcolor">
        <div class="grid grid-cols-12">
          <div class="xxl:col-span-4 xl:col-span-4 lg:col-span-4 md:col-span-3 sm:col-span-2"></div>
          <div class="xxl:col-span-4 xl:col-span-4 lg:col-span-4 md:col-span-6 sm:col-span-8 col-span-12">
                <div class="my-[2.5rem] flex justify-center">
                   
                </div>
                <div class="box">
                    <div class="box-body !p-[3rem]">
                        <p class="h5 font-semibold mb-2 text-center">Change Password</p>
                        
                        @if (session('success'))
                            <div class="alert alert-success mb-4" role="alert">
                                {{ session('success') }}
                            </div>
                        @endif
                        
                        @if ($errors->any())
                            <div class="alert alert-danger mb-4">
                                <ul class="mb-0">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        
                        <form method="POST" action="{{ route('user.password.update') }}">
                            @csrf
                            
                            <div class="grid grid-cols-12">
                                <div class="xl:col-span-12 col-span-12 mb-3">
                                    <label for="current_password" class="form-label text-default">Current Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control form-control-lg @error('current_password') is-invalid @enderror !border-s border-defaultborder dark:border-defaultborder/10 !rounded-e-none" id="current_password" name="current_password" required autocomplete="current-password">
                                        <button aria-label="button" class="ti-btn ti-btn-light !mb-0 !rounded-s-none" type="button" onclick="createpassword('current_password',this)" id="button-addon2">
                                            <i class="ri-eye-off-line align-middle"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="xl:col-span-12 col-span-12 mb-3">
                                    <label for="new_password" class="form-label text-default">New Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control form-control-lg @error('new_password') is-invalid @enderror !border-s border-defaultborder dark:border-defaultborder/10 !rounded-e-none" id="new_password" name="new_password" required>
                                        <button aria-label="button" class="ti-btn ti-btn-light !mb-0 !rounded-s-none" type="button" onclick="createpassword('new_password',this)" id="button-addon21">
                                            <i class="ri-eye-off-line align-middle"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="xl:col-span-12 col-span-12 mb-3">
                                    <label for="confirm_password" class="form-label text-default">Confirm Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control form-control-lg !border-s border-defaultborder dark:border-defaultborder/10 !rounded-e-none" id="confirm_password" name="confirm_password" required>
                                        <button aria-label="button" class="ti-btn ti-btn-light !mb-0 !rounded-s-none" type="button" onclick="createpassword('confirm_password',this)" id="button-addon22">
                                            <i class="ri-eye-off-line align-middle"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="xl:col-span-12 col-span-12 grid">
                                    <button type="submit" class="ti-btn ti-btn-lg bg-primary text-white !font-medium dark:border-defaultborder/10">
                                        Update Password
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
          </div>
          <div class="xxl:col-span-4 xl:col-span-4 lg:col-span-4 md:col-span-3 sm:col-span-2"></div>
        </div>
      </div>
  </div>

  <script>
    function createpassword(id, el) {
        let x = document.getElementById(id);
        if (x.type === "password") {
            x.type = "text";
            el.innerHTML = '<i class="ri-eye-line align-middle"></i>';
        } else {
            x.type = "password";
            el.innerHTML = '<i class="ri-eye-off-line align-middle"></i>';
        }
    }
  </script>
@endsection