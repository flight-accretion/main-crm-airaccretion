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
    <div id="loader">
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
                            <p class="h5 font-semibold mb-2 text-center">Sign In</p>
                            
                            @if($errors->any())
                                <div class="mb-4 bg-danger/10 border border-danger/20 text-danger dark:text-danger/80 rounded-md p-4">
                                    <ul class="list-disc list-inside">
                                        @foreach ($errors->all() as $error)
                                           <center>{{ $error }}</center>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                            
                            <form method="POST" action="{{ route('login') }}">
                                @csrf
                                <div class="grid grid-cols-12">
                                    <div class="xl:col-span-12 col-span-12 mb-3">
                                        <label for="email" class="form-label text-default">Email</label>
                                        <input type="email" class="form-control form-control-lg w-full !rounded-md" id="email" name="email" placeholder="email@example.com" value="{{ old('email') }}" required autofocus>
                                    </div>
                                    <div class="xl:col-span-12 col-span-12 mb-3">
                                        <a href="{{ route('password.email') }}" class="ltr:float-right rtl:float-left text-danger">Forgot password ?</a>
                                        <label for="password" class="form-label text-default block">Password</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control !border-s border-defaultborder dark:border-defaultborder/10 form-control-lg !rounded-s-md" id="password" name="password" placeholder="password" required>
                                            <button aria-label="button" class="ti-btn ti-btn-light !rounded-s-none !mb-0" type="button" onclick="createpassword('password',this)" id="button-addon2"><i class="ri-eye-off-line align-middle"></i></button>
                                        </div>
                                        <!-- <div class="mt-2">
                                            <div class="form-check !ps-0">
                                                <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                                                <label class="form-check-label text-[#8c9097] dark:text-white/50 font-normal" for="remember">
                                                    Remember me
                                                </label>
                                            </div>
                                        </div> -->
                                    </div>
                                    <div class="xl:col-span-12 col-span-12 grid mt-2">
                                        <button type="submit" class="ti-btn ti-btn-primary !bg-primary !text-white !font-medium">Sign In</button>
                                    </div>
                                </div>
                            </form>
                            <div class="text-center my-4 authentication-barrier">
                                <span>OR</span>
                            </div>
                            <div class="btn-list text-center">
                                <button aria-label="button" type="button" class="ti-btn ti-btn-primary me-[0.365rem] !py-1 !px-2 !text-[0.75rem]">
                                   <a href="https://flights.airaccretion.com/" target="_blank"> Flying Calculation </a>
                                </button>
                                <button aria-label="button" type="button" class="ti-btn ti-btn-primary me-[0.365rem] !py-1 !px-2 !text-[0.75rem]">
                                    <a href="http://airpoints.airaccretion.com/" target="_blank"> Airpoints </a>
                                </button>                             
                            </div>
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