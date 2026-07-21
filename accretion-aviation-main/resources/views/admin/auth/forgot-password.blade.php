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
                      <p class="h5 font-semibold mb-2 text-center">Forgot Password</p>
                      @if (session('status'))
                          <div class="mb-4 text-success">
                              {{ session('status') }}
                          </div>
                      @endif
                      <form method="POST" action="{{ route('password.email') }}">
                          @csrf
                          <div class="grid grid-cols-12">
                              <div class="xl:col-span-12 col-span-12 mb-3">
                                  <label for="email" class="form-label text-default">Email Address</label>
                                  <input type="email" class="form-control form-control-lg w-full !rounded-md" id="email" name="email" placeholder="Enter your email" required>
                                  @error('email')
                                      <span class="text-danger">{{ $message }}</span>
                                  @enderror
                              </div>
                              
                              <div class="xl:col-span-12 col-span-12 grid mt-2">
                                  <button type="submit" class="ti-btn ti-btn-primary !bg-primary !text-white !font-medium">Send Reset Password Link</button>
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