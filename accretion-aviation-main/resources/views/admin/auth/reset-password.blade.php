<!DOCTYPE html>
<html lang="en" dir="ltr" data-nav-layout="vertical" data-vertical-style="overlay" class="light" data-header-styles="light" data-menu-styles="light" data-toggled="close">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> Accretion Aviation </title>
    <meta name="description" content="">
    <meta name="keywords" content="">

    <!-- Favicon -->
    <link rel="shortcut icon" href="/assets/admin/images/favicon.svg">

    <!-- Main Theme Js -->
    <script src="/assets/admin/js/authentication-main.js"></script>

    <!-- Style Css -->
    <link rel="stylesheet" href="/assets/admin/css/style.css">

    <!-- Simplebar Css -->
    <link id="style" href="/assets/admin/libs/simplebar/simplebar.min.css" rel="stylesheet">

    <!-- Color Picker Css -->
    <link rel="stylesheet" href="/assets/admin/libs/@simonwep/pickr/themes/nano.min.css">

    <!-- Swiper Css -->
    <link rel="stylesheet" href="/assets/admin/libs/swiper/swiper-bundle.min.css">


</head>

<body class="">
  
    <!-- Loader -->
    <div id="loader" >
        <img src="/assets/admin/images/media/loader.svg" alt="">
    </div>
    <!-- Loader -->

    <div class="container">
        <div class="flex justify-center authentication authentication-basic items-center h-full text-defaultsize text-defaulttextcolor">
          <div class="grid grid-cols-12">
            <div class="xxl:col-span-4 xl:col-span-4 lg:col-span-4 md:col-span-3 sm:col-span-2"></div>
            <div class="xxl:col-span-4 xl:col-span-4 lg:col-span-4 md:col-span-6 sm:col-span-8 col-span-12">
                <div class="my-[2.5rem] flex justify-center">
                    <a href="javascript:void(0)">
                        <img src="/assets/admin/images/logo.png" alt="logo" class="desktop-logo">
                        <img src="/assets/admin/images/logo.png" alt="logo" class="desktop-dark">
                    </a>
                </div>
               <div class="box">
                  <div class="box-body !p-[3rem]">
                      <p class="h5 font-semibold mb-2 text-center">Reset Password</p>
                      @if (session('status'))
                          <div class="mb-4 text-success">
                              {{ session('status') }}
                          </div>
                      @endif
                      <form method="POST" action="{{ route('password.update') }}">
                          @csrf
                          <input type="hidden" name="token" value="{{ $token }}">
                          <div class="grid grid-cols-12">
                              <div class="xl:col-span-12 col-span-12 mb-3">
                                  <label class="form-label text-default">Email Address</label>
                                  <input type="email" class="form-control form-control-lg w-full !rounded-md" name="email" value="{{ $email ?? old('email') }}" required autocomplete="email">
                                  @error('email')
                                      <span class="text-danger">{{ $message }}</span>
                                  @enderror
                              </div>
                              <div class="xl:col-span-12 col-span-12 mb-3">
                                  <label for="password" class="form-label text-default">New Password</label>
                                  <div class="input-group">
                                      <input type="password" class="form-control form-control-lg !border-s border-defaultborder dark:border-defaultborder/10 !rounded-e-none" id="password" name="password" placeholder="new password" required>
                                      <button aria-label="button" class="ti-btn ti-btn-light !mb-0 !rounded-s-none" onclick="createpassword('password',this)" type="button" id="button-addon21"><i class="ri-eye-off-line align-middle"></i></button>
                                  </div>
                                  @error('password')
                                      <span class="text-danger">{{ $message }}</span>
                                  @enderror
                              </div>
                              <div class="xl:col-span-12 col-span-12 mb-3">
                                  <label for="password_confirmation" class="form-label text-default">Confirm Password</label>
                                  <div class="input-group">
                                      <input type="password" class="form-control form-control-lg !border-s border-defaultborder dark:border-defaultborder/10 !rounded-e-none" id="password_confirmation" name="password_confirmation" placeholder="confirm password" required>
                                      <button aria-label="button" class="ti-btn ti-btn-light !mb-0 !rounded-s-none" onclick="createpassword('password_confirmation',this)" type="button" id="button-addon22"><i class="ri-eye-off-line align-middle"></i></button>
                                  </div>
                              </div>
                              <div class="xl:col-span-12 col-span-12 grid mt-2">
                                  <button type="submit" class="ti-btn ti-btn-primary !bg-primary !text-white !font-medium">Reset Password</button>
                              </div>
                              <div class="xl:col-span-12 col-span-12 text-center mt-4">
                                <p class="text-sm text-muted">
                                    Already have an account?
                                    <a href="{{ route('login') }}" class="text-primary font-semibold hover:underline">
                                        Sign in
                                    </a>
                                </p>
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

    <!-- Show Password JS -->
    <script src="/assets/admin/js/show-password.js"></script>

    <!-- Auth Custom JS -->
    <script src="/assets/admin/js/auth-custom.js"></script>

</body>

</html>