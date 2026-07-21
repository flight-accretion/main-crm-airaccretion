<!DOCTYPE html>
<html lang="en" dir="ltr" data-nav-layout="vertical" class="light" data-header-styles="light"
    data-menu-styles="light" data-toggled="close">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title> Accretion Aviation </title>
    <meta name="description" content="">
    <meta name="keywords" content="">

    <!-- Favicon -->
    <link rel="shortcut icon" href="/assets/admin/images/favicon.svg">

    <!-- Style Css -->
    <link rel="stylesheet" href="/assets/admin/css/style.css?v=1.4">
    <link rel="stylesheet" href="/assets/admin/css/responsive.css?v=1.1">
    <link rel="stylesheet" href="/assets/admin/css/custom.css?v=2.1">

    <!-- Simplebar Css -->
    <link rel="stylesheet" href="/assets/admin/libs/simplebar/simplebar.min.css">

    <!-- Color Picker Css -->
    <link rel="stylesheet" href="/assets/admin/libs/@simonwep/pickr/themes/nano.min.css">

    <!-- FlatPickr CSS -->
    <link rel="stylesheet" href="/assets/admin/libs/flatpickr/flatpickr.min.css">

    <link rel="stylesheet" href="/assets/admin/libs/quill/quill.snow.css">
    <link rel="stylesheet" href="/assets/admin/libs/quill/quill.bubble.css">

    <link rel="stylesheet" href="/assets/admin/libs/fullcalendar/main.min.css">

    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/css/intlTelInput.css">


</head>

<body>

    <!-- ========== Switcher  ========== -->
    <div id="hs-overlay-switcher" class="hs-overlay hidden ti-offcanvas ti-offcanvas-right" tabindex="-1">
        <div class="ti-offcanvas-header z-10 relative">
            <h5 class="ti-offcanvas-title">
                Switcher
            </h5>
            <button type="button"
                class="ti-btn flex-shrink-0 p-0 !mb-0  transition-none text-defaulttextcolor dark:text-defaulttextcolor/70 hover:text-gray-700 focus:ring-gray-400 focus:ring-offset-white  dark:hover:text-white/80 dark:focus:ring-white/10 dark:focus:ring-offset-white/10"
                data-hs-overlay="#hs-overlay-switcher">
                <span class="sr-only">Close modal</span>
                <i class="ri-close-circle-line leading-none text-lg"></i>
            </button>
        </div>
        <div class="ti-offcanvas-body !p-0 !border-b dark:border-white/10 z-10 relative !h-auto">
            <div class="flex rtl:space-x-reverse" aria-label="Tabs" role="tablist">
                <button type="button"
                    class="hs-tab-active:bg-success/20 w-full !py-2 !px-4 hs-tab-active:border-b-transparent text-defaultsize border-0 hs-tab-active:text-success dark:hs-tab-active:bg-success/20 dark:hs-tab-active:border-b-white/10 dark:hs-tab-active:text-success -mb-px bg-white font-semibold text-center  text-defaulttextcolor dark:text-defaulttextcolor/70 rounded-none hover:text-gray-700 dark:bg-bodybg dark:border-white/10  active"
                    id="switcher-item-1" data-hs-tab="#switcher-1" aria-controls="switcher-1" role="tab">
                    Theme Style
                </button>
                <button type="button"
                    class="hs-tab-active:bg-success/20 w-full !py-2 !px-4 hs-tab-active:border-b-transparent text-defaultsize border-0 hs-tab-active:text-success dark:hs-tab-active:bg-success/20 dark:hs-tab-active:border-b-white/10 dark:hs-tab-active:text-success -mb-px  bg-white font-semibold text-center  text-defaulttextcolor dark:text-defaulttextcolor/70 rounded-none hover:text-gray-700 dark:bg-bodybg dark:border-white/10  dark:hover:text-gray-300"
                    id="switcher-item-2" data-hs-tab="#switcher-2" aria-controls="switcher-2" role="tab">
                    Theme Colors
                </button>
            </div>
        </div>
        <div class="ti-offcanvas-body" id="switcher-body">
            <div id="switcher-1" role="tabpanel" aria-labelledby="switcher-item-1" class="">
                <div class="">
                    <p class="switcher-style-head">Theme Color Mode:</p>
                    <div class="grid grid-cols-3 switcher-style">
                        <div class="flex items-center">
                            <input type="radio" name="theme-style" class="ti-form-radio" id="switcher-light-theme"
                                checked>
                            <label for="switcher-light-theme"
                                class="text-defaultsize text-defaulttextcolor dark:text-defaulttextcolor/70 ms-2  font-semibold">Light</label>
                        </div>
                        <div class="flex items-center">
                            <input type="radio" name="theme-style" class="ti-form-radio" id="switcher-dark-theme">
                            <label for="switcher-dark-theme"
                                class="text-defaultsize text-defaulttextcolor dark:text-defaulttextcolor/70 ms-2  font-semibold">Dark</label>
                        </div>
                    </div>
                </div>
                <div>
                    <p class="switcher-style-head">Directions:</p>
                    <div class="grid grid-cols-3  switcher-style">
                        <div class="flex items-center">
                            <input type="radio" name="direction" class="ti-form-radio" id="switcher-ltr" checked>
                            <label for="switcher-ltr"
                                class="text-defaultsize text-defaulttextcolor dark:text-defaulttextcolor/70 ms-2  font-semibold">LTR</label>
                        </div>
                        <div class="flex items-center">
                            <input type="radio" name="direction" class="ti-form-radio" id="switcher-rtl">
                            <label for="switcher-rtl"
                                class="text-defaultsize text-defaulttextcolor dark:text-defaulttextcolor/70 ms-2  font-semibold">RTL</label>
                        </div>
                    </div>
                </div>
                <div>
                    <p class="switcher-style-head">Navigation Styles:</p>
                    <div class="grid grid-cols-3  switcher-style">
                        <div class="flex items-center">
                            <input type="radio" name="navigation-style" class="ti-form-radio"
                                id="switcher-vertical" checked>
                            <label for="switcher-vertical"
                                class="text-defaultsize text-defaulttextcolor dark:text-defaulttextcolor/70 ms-2  font-semibold">Vertical</label>
                        </div>
                        <div class="flex items-center">
                            <input type="radio" name="navigation-style" class="ti-form-radio"
                                id="switcher-horizontal">
                            <label for="switcher-horizontal"
                                class="text-defaultsize text-defaulttextcolor dark:text-defaulttextcolor/70 ms-2  font-semibold">Horizontal</label>
                        </div>
                    </div>
                </div>
                <div>
                    <p class="switcher-style-head">Navigation Menu Style:</p>
                    <div class="grid grid-cols-2 gap-2 switcher-style">
                        <div class="flex">
                            <input type="radio" name="navigation-data-menu-styles" class="ti-form-radio"
                                id="switcher-menu-click" checked>
                            <label for="switcher-menu-click"
                                class="text-defaultsize text-defaulttextcolor dark:text-defaulttextcolor/70 ms-2  font-semibold">Menu
                                Click</label>
                        </div>
                        <div class="flex">
                            <input type="radio" name="navigation-data-menu-styles" class="ti-form-radio"
                                id="switcher-menu-hover">
                            <label for="switcher-menu-hover"
                                class="text-defaultsize text-defaulttextcolor dark:text-defaulttextcolor/70 ms-2  font-semibold">Menu
                                Hover</label>
                        </div>
                        <div class="flex">
                            <input type="radio" name="navigation-data-menu-styles" class="ti-form-radio"
                                id="switcher-icon-click">
                            <label for="switcher-icon-click"
                                class="text-defaultsize text-defaulttextcolor dark:text-defaulttextcolor/70 ms-2  font-semibold">Icon
                                Click</label>
                        </div>
                        <div class="flex">
                            <input type="radio" name="navigation-data-menu-styles" class="ti-form-radio"
                                id="switcher-icon-hover">
                            <label for="switcher-icon-hover"
                                class="text-defaultsize text-defaulttextcolor dark:text-defaulttextcolor/70 ms-2  font-semibold">Icon
                                Hover</label>
                        </div>
                    </div>
                    <div class="px-4 text-secondary text-xs"><b class="me-2">Note:</b>Works same for both Vertical
                        and
                        Horizontal
                    </div>
                </div>
                <div class=" sidemenu-layout-styles">
                    <p class="switcher-style-head">Sidemenu Layout Syles:</p>
                    <div class="grid grid-cols-2 gap-2 switcher-style">
                        <div class="flex">
                            <input type="radio" name="sidemenu-layout-styles" class="ti-form-radio"
                                id="switcher-default-menu" checked>
                            <label for="switcher-default-menu"
                                class="text-defaultsize text-defaulttextcolor dark:text-defaulttextcolor/70 ms-2  font-semibold ">Default
                                Menu</label>
                        </div>
                        <div class="flex">
                            <input type="radio" name="sidemenu-layout-styles" class="ti-form-radio"
                                id="switcher-closed-menu">
                            <label for="switcher-closed-menu"
                                class="text-defaultsize text-defaulttextcolor dark:text-defaulttextcolor/70 ms-2  font-semibold ">
                                Closed
                                Menu</label>
                        </div>
                        <div class="flex">
                            <input type="radio" name="sidemenu-layout-styles" class="ti-form-radio"
                                id="switcher-icontext-menu">
                            <label for="switcher-icontext-menu"
                                class="text-defaultsize text-defaulttextcolor dark:text-defaulttextcolor/70 ms-2  font-semibold ">Icon
                                Text</label>
                        </div>
                        <div class="flex">
                            <input type="radio" name="sidemenu-layout-styles" class="ti-form-radio"
                                id="switcher-icon-overlay">
                            <label for="switcher-icon-overlay"
                                class="text-defaultsize text-defaulttextcolor dark:text-defaulttextcolor/70 ms-2  font-semibold ">Icon
                                Overlay</label>
                        </div>
                        <div class="flex">
                            <input type="radio" name="sidemenu-layout-styles" class="ti-form-radio"
                                id="switcher-detached">
                            <label for="switcher-detached"
                                class="text-defaultsize text-defaulttextcolor dark:text-defaulttextcolor/70 ms-2  font-semibold ">Detached</label>
                        </div>
                        <div class="flex">
                            <input type="radio" name="sidemenu-layout-styles" class="ti-form-radio"
                                id="switcher-double-menu">
                            <label for="switcher-double-menu"
                                class="text-defaultsize text-defaulttextcolor dark:text-defaulttextcolor/70 ms-2  font-semibold">Double
                                Menu</label>
                        </div>
                    </div>
                    <div class="px-4 text-secondary text-xs"><b class="me-2">Note:</b>Navigation menu styles won't
                        work
                        here.</div>
                </div>
                <div>
                    <p class="switcher-style-head">Page Styles:</p>
                    <div class="grid grid-cols-3  switcher-style">
                        <div class="flex">
                            <input type="radio" name="data-page-styles" class="ti-form-radio"
                                id="switcher-regular" checked>
                            <label for="switcher-regular"
                                class="text-defaultsize text-defaulttextcolor dark:text-defaulttextcolor/70 ms-2  font-semibold">Regular</label>
                        </div>
                        <div class="flex">
                            <input type="radio" name="data-page-styles" class="ti-form-radio"
                                id="switcher-classic">
                            <label for="switcher-classic"
                                class="text-defaultsize text-defaulttextcolor dark:text-defaulttextcolor/70 ms-2  font-semibold">Classic</label>
                        </div>
                        <div class="flex">
                            <input type="radio" name="data-page-styles" class="ti-form-radio"
                                id="switcher-modern">
                            <label for="switcher-modern"
                                class="text-defaultsize text-defaulttextcolor dark:text-defaulttextcolor/70 ms-2  font-semibold">
                                Modern</label>
                        </div>
                    </div>
                </div>
                <div>
                    <p class="switcher-style-head">Layout Width Styles:</p>
                    <div class="grid grid-cols-3 switcher-style">
                        <div class="flex">
                            <input type="radio" name="layout-width" class="ti-form-radio" id="switcher-full-width"
                                checked>
                            <label for="switcher-full-width"
                                class="text-defaultsize text-defaulttextcolor dark:text-defaulttextcolor/70 ms-2  font-semibold">FullWidth</label>
                        </div>
                        <div class="flex">
                            <input type="radio" name="layout-width" class="ti-form-radio" id="switcher-boxed">
                            <label for="switcher-boxed"
                                class="text-defaultsize text-defaulttextcolor dark:text-defaulttextcolor/70 ms-2  font-semibold">Boxed</label>
                        </div>
                    </div>
                </div>
                <div>
                    <p class="switcher-style-head">Menu Positions:</p>
                    <div class="grid grid-cols-3  switcher-style">
                        <div class="flex">
                            <input type="radio" name="data-menu-positions" class="ti-form-radio"
                                id="switcher-menu-fixed" checked>
                            <label for="switcher-menu-fixed"
                                class="text-defaultsize text-defaulttextcolor dark:text-defaulttextcolor/70 ms-2  font-semibold">Fixed</label>
                        </div>
                        <div class="flex">
                            <input type="radio" name="data-menu-positions" class="ti-form-radio"
                                id="switcher-menu-scroll">
                            <label for="switcher-menu-scroll"
                                class="text-defaultsize text-defaulttextcolor dark:text-defaulttextcolor/70 ms-2  font-semibold">Scrollable
                            </label>
                        </div>
                    </div>
                </div>
                <div>
                    <p class="switcher-style-head">Header Positions:</p>
                    <div class="grid grid-cols-3 switcher-style">
                        <div class="flex">
                            <input type="radio" name="data-header-positions" class="ti-form-radio"
                                id="switcher-header-fixed" checked>
                            <label for="switcher-header-fixed"
                                class="text-defaultsize text-defaulttextcolor dark:text-defaulttextcolor/70 ms-2  font-semibold">
                                Fixed</label>
                        </div>
                        <div class="flex">
                            <input type="radio" name="data-header-positions" class="ti-form-radio"
                                id="switcher-header-scroll">
                            <label for="switcher-header-scroll"
                                class="text-defaultsize text-defaulttextcolor dark:text-defaulttextcolor/70 ms-2  font-semibold">Scrollable
                            </label>
                        </div>
                    </div>
                </div>
                <div class="">
                    <p class="switcher-style-head">Loader:</p>
                    <div class="grid grid-cols-3 switcher-style">
                        <div class="flex">
                            <input type="radio" name="page-loader" class="ti-form-radio"
                                id="switcher-loader-enable" checked>
                            <label for="switcher-loader-enable"
                                class="text-defaultsize text-defaulttextcolor dark:text-defaulttextcolor/70 ms-2  font-semibold">
                                Enable</label>
                        </div>
                        <div class="flex">
                            <input type="radio" name="page-loader" class="ti-form-radio"
                                id="switcher-loader-disable">
                            <label for="switcher-loader-disable"
                                class="text-defaultsize text-defaulttextcolor dark:text-defaulttextcolor/70 ms-2  font-semibold">Disable
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <div id="switcher-2" class="hidden" role="tabpanel" aria-labelledby="switcher-item-2">
                <div class="theme-colors">
                    <p class="switcher-style-head">Menu Colors:</p>
                    <div class="flex switcher-style space-x-3 rtl:space-x-reverse">
                        <div class="hs-tooltip ti-main-tooltip ti-form-radio switch-select ">
                            <input class="hs-tooltip-toggle ti-form-radio color-input color-white" type="radio"
                                name="menu-colors" id="switcher-menu-light" checked>
                            <span
                                class="hs-tooltip-content ti-main-tooltip-content !py-1 !px-2 !bg-black text-xs font-medium !text-white shadow-sm dark:!bg-black"
                                role="tooltip">
                                Light Menu
                            </span>
                        </div>
                        <div class="hs-tooltip ti-main-tooltip ti-form-radio switch-select ">
                            <input class="hs-tooltip-toggle ti-form-radio color-input color-dark" type="radio"
                                name="menu-colors" id="switcher-menu-dark" checked>
                            <span
                                class="hs-tooltip-content ti-main-tooltip-content !py-1 !px-2 !bg-black text-xs font-medium !text-white shadow-sm dark:!bg-black"
                                role="tooltip">
                                Dark Menu
                            </span>
                        </div>
                        <div class="hs-tooltip ti-main-tooltip ti-form-radio switch-select ">
                            <input class="hs-tooltip-toggle ti-form-radio color-input color-primary" type="radio"
                                name="menu-colors" id="switcher-menu-primary">
                            <span
                                class="hs-tooltip-content ti-main-tooltip-content !py-1 !px-2 !bg-black text-xs font-medium !text-white shadow-sm dark:!bg-black"
                                role="tooltip">
                                Color Menu
                            </span>
                        </div>
                        <div class="hs-tooltip ti-main-tooltip ti-form-radio switch-select ">
                            <input class="hs-tooltip-toggle ti-form-radio color-input color-gradient" type="radio"
                                name="menu-colors" id="switcher-menu-gradient">
                            <span
                                class="hs-tooltip-content ti-main-tooltip-content !py-1 !px-2 !bg-black text-xs font-medium !text-white shadow-sm dark:!bg-black"
                                role="tooltip">
                                Gradient Menu
                            </span>
                        </div>
                        <div class="hs-tooltip ti-main-tooltip ti-form-radio switch-select ">
                            <input class="hs-tooltip-toggle ti-form-radio color-input color-transparent"
                                type="radio" name="menu-colors" id="switcher-menu-transparent">
                            <span
                                class="hs-tooltip-content ti-main-tooltip-content !py-1 !px-2 !bg-black text-xs font-medium !text-white shadow-sm dark:!bg-black"
                                role="tooltip">
                                Transparent Menu
                            </span>
                        </div>
                    </div>
                    <div class="px-4 text-[#8c9097] dark:text-white/50 text-[.6875rem]"><b class="me-2">Note:</b>If
                        you want to change color Menu
                        dynamically
                        change from below Theme Primary color picker.</div>
                </div>
                <div class="theme-colors">
                    <p class="switcher-style-head">Header Colors:</p>
                    <div class="flex switcher-style space-x-3 rtl:space-x-reverse">
                        <div class="hs-tooltip ti-main-tooltip ti-form-radio switch-select ">
                            <input class="hs-tooltip-toggle ti-form-radio color-input color-white !border"
                                type="radio" name="header-colors" id="switcher-header-light" checked>
                            <span
                                class="hs-tooltip-content ti-main-tooltip-content !py-1 !px-2 !bg-black text-xs font-medium !text-white shadow-sm dark:!bg-black"
                                role="tooltip">
                                Light Header
                            </span>
                        </div>
                        <div class="hs-tooltip ti-main-tooltip ti-form-radio switch-select ">
                            <input class="hs-tooltip-toggle ti-form-radio color-input color-dark" type="radio"
                                name="header-colors" id="switcher-header-dark">
                            <span
                                class="hs-tooltip-content ti-main-tooltip-content !py-1 !px-2 !bg-black text-xs font-medium !text-white shadow-sm dark:!bg-black"
                                role="tooltip">
                                Dark Header
                            </span>
                        </div>
                        <div class="hs-tooltip ti-main-tooltip ti-form-radio switch-select ">
                            <input class="hs-tooltip-toggle ti-form-radio color-input color-primary" type="radio"
                                name="header-colors" id="switcher-header-primary">
                            <span
                                class="hs-tooltip-content ti-main-tooltip-content !py-1 !px-2 !bg-black text-xs font-medium !text-white shadow-sm dark:!bg-black"
                                role="tooltip">
                                Color Header
                            </span>
                        </div>
                        <div class="hs-tooltip ti-main-tooltip ti-form-radio switch-select ">
                            <input class="hs-tooltip-toggle ti-form-radio color-input color-gradient" type="radio"
                                name="header-colors" id="switcher-header-gradient">
                            <span
                                class="hs-tooltip-content ti-main-tooltip-content !py-1 !px-2 !bg-black text-xs font-medium !text-white shadow-sm dark:!bg-black"
                                role="tooltip">
                                Gradient Header
                            </span>
                        </div>
                        <div class="hs-tooltip ti-main-tooltip ti-form-radio switch-select ">
                            <input class="hs-tooltip-toggle ti-form-radio color-input color-transparent"
                                type="radio" name="header-colors" id="switcher-header-transparent">
                            <span
                                class="hs-tooltip-content ti-main-tooltip-content !py-1 !px-2 !bg-black text-xs font-medium !text-white shadow-sm dark:!bg-black"
                                role="tooltip">
                                Transparent Header
                            </span>
                        </div>
                    </div>
                    <div class="px-4 text-[#8c9097] dark:text-white/50 text-[.6875rem]"><b class="me-2">Note:</b>If
                        you want to change color
                        Header dynamically
                        change from below Theme Primary color picker.</div>
                </div>
                <div class="theme-colors">
                    <p class="switcher-style-head">Theme Primary:</p>
                    <div class="flex switcher-style space-x-3 rtl:space-x-reverse">
                        <div class="ti-form-radio switch-select">
                            <input class="ti-form-radio color-input color-primary-1" type="radio"
                                name="theme-primary" id="switcher-primary" checked>
                        </div>
                        <div class="ti-form-radio switch-select">
                            <input class="ti-form-radio color-input color-primary-2" type="radio"
                                name="theme-primary" id="switcher-primary1">
                        </div>
                        <div class="ti-form-radio switch-select">
                            <input class="ti-form-radio color-input color-primary-3" type="radio"
                                name="theme-primary" id="switcher-primary2">
                        </div>
                        <div class="ti-form-radio switch-select">
                            <input class="ti-form-radio color-input color-primary-4" type="radio"
                                name="theme-primary" id="switcher-primary3">
                        </div>
                        <div class="ti-form-radio switch-select">
                            <input class="ti-form-radio color-input color-primary-5" type="radio"
                                name="theme-primary" id="switcher-primary4">
                        </div>
                        <div class="ti-form-radio switch-select ps-0 mt-1 color-primary-light">
                            <div class="theme-container-primary"></div>
                            <div class="pickr-container-primary"></div>
                        </div>
                    </div>
                </div>
                <div class="theme-colors">
                    <p class="switcher-style-head">Theme Background:</p>
                    <div class="flex switcher-style space-x-3 rtl:space-x-reverse">
                        <div class="ti-form-radio switch-select">
                            <input class="ti-form-radio color-input color-bg-1" type="radio"
                                name="theme-background" id="switcher-background" checked>
                        </div>
                        <div class="ti-form-radio switch-select">
                            <input class="ti-form-radio color-input color-bg-2" type="radio"
                                name="theme-background" id="switcher-background1">
                        </div>
                        <div class="ti-form-radio switch-select">
                            <input class="ti-form-radio color-input color-bg-3" type="radio"
                                name="theme-background" id="switcher-background2">
                        </div>
                        <div class="ti-form-radio switch-select">
                            <input class="ti-form-radio color-input color-bg-4" type="radio"
                                name="theme-background" id="switcher-background3">
                        </div>
                        <div class="ti-form-radio switch-select">
                            <input class="ti-form-radio color-input color-bg-5" type="radio"
                                name="theme-background" id="switcher-background4">
                        </div>
                        <div class="ti-form-radio switch-select ps-0 mt-1 color-bg-transparent">
                            <div class="theme-container-background hidden"></div>
                            <div class="pickr-container-background"></div>
                        </div>
                    </div>
                </div>
                <div class="menu-image theme-colors">
                    <p class="switcher-style-head">Menu With Background Image:</p>
                    <div class="flex switcher-style space-x-3 rtl:space-x-reverse flex-wrap gap-3">
                        <div class="ti-form-radio switch-select">
                            <input class="ti-form-radio bgimage-input bg-img1" type="radio" name="theme-images"
                                id="switcher-bg-img">
                        </div>
                        <div class="ti-form-radio switch-select">
                            <input class="ti-form-radio bgimage-input bg-img2" type="radio" name="theme-images"
                                id="switcher-bg-img1">
                        </div>
                        <div class="ti-form-radio switch-select">
                            <input class="ti-form-radio bgimage-input bg-img3" type="radio" name="theme-images"
                                id="switcher-bg-img2">
                        </div>
                        <div class="ti-form-radio switch-select">
                            <input class="ti-form-radio bgimage-input bg-img4" type="radio" name="theme-images"
                                id="switcher-bg-img3">
                        </div>
                        <div class="ti-form-radio switch-select">
                            <input class="ti-form-radio bgimage-input bg-img5" type="radio" name="theme-images"
                                id="switcher-bg-img4">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="ti-offcanvas-footer sm:flex justify-between">
            <a href="javascript:void(0);" id="reset-all" class="w-full ti-btn ti-btn-danger-full m-1">Reset</a>
        </div>
    </div>
    <!-- ========== END Switcher  ========== -->

    <!-- Loader -->
    <div id="loader">
        <img src="/assets/admin/images/media/loader.svg" alt="">
    </div>
    <!-- Loader -->

    <div class="page">

        <!-- Start::Header -->
        <header class="app-header">
            <nav class="main-header !h-[3.75rem]" aria-label="Global">
                <div class="main-header-container ps-[0.725rem] pe-[1rem] ">

                    <div class="header-content-left">
                        <!-- Start::header-element -->
                        <div class="header-element">
                            <div class="horizontal-logo">
                                <a href="javascript:void(0)" class="header-logo">
                                    <img src="/assets/admin/images/logo.png" alt="logo" class="desktop-logo">
                                    <img src="/assets/admin/images/logo.png" alt="logo" class="toggle-logo">
                                    <img src="/assets/admin/images/logo.png" alt="logo" class="desktop-dark">
                                    <img src="/assets/admin/images/logo.png" alt="logo" class="toggle-dark">
                                    <img src="/assets/admin/images/logo.png" alt="logo" class="desktop-white">
                                    <img src="/assets/admin/images/logo.png" alt="logo" class="toggle-white">
                                </a>
                            </div>
                        </div>
                        <!-- End::header-element -->
                        <!-- Start::header-element -->
                        <div class="header-element md:px-[0.325rem] !items-center">
                            <!-- Start::header-link -->
                            <a aria-label="Hide Sidebar"
                                class="sidemenu-toggle animated-arrow  hor-toggle horizontal-navtoggle inline-flex items-center"
                                href="javascript:void(0);"><span></span></a>
                            <!-- End::header-link -->
                        </div>
                        <!-- End::header-element -->
                    </div>



                    <div class="header-content-right">

                        {{-- <div class="header-element py-[1rem] md:px-[0.65rem] px-2 header-search">
                            <button aria-label="button" type="button" data-hs-overlay="#search-modal"
                                class="inline-flex flex-shrink-0 justify-center items-center gap-2  rounded-full font-medium focus:ring-offset-0 focus:ring-offset-white transition-all text-xs dark:bg-bgdark dark:hover:bg-black/20 dark:text-[#8c9097] dark:text-white/50 dark:hover:text-white dark:focus:ring-white/10 dark:focus:ring-offset-white/10">
                                <i class="bx bx-search-alt-2 header-link-icon"></i>
                            </button>
                        </div> --}}

                        <!-- start header country -->
                        {{-- <div
                            class="header-element py-[1rem] md:px-[0.65rem] px-2  header-country hs-dropdown ti-dropdown  hidden sm:block [--placement:bottom-right] rtl:[--placement:bottom-left]">
                            <button id="dropdown-flag" type="button"
                                class="hs-dropdown-toggle ti-dropdown-toggle !p-0 flex-shrink-0  !border-0 !rounded-full !shadow-none">
                                <img src="/assets/admin/images/flags/us_flag.jpg" alt="flag-img"
                                    class="h-[1.25rem] w-[1.25rem] rounded-full">
                            </button>

                            <div class="hs-dropdown-menu ti-dropdown-menu min-w-[10rem] hidden !-mt-3"
                                aria-labelledby="dropdown-flag">
                                <div class="ti-dropdown-divider divide-y divide-gray-200 dark:divide-white/10">
                                    <div class="py-2 first:pt-0 last:pb-0">
                                        <div class="ti-dropdown-item !p-[0.65rem] ">
                                            <div class="flex items-center space-x-2 rtl:space-x-reverse w-full">
                                                <div class="h-[1.375rem] flex items-center w-[1.375rem] rounded-full">
                                                    <img src="/assets/admin/images/flags/us_flag.jpg" alt="flag-img"
                                                        class="h-[1rem] w-[1rem] rounded-full">
                                                </div>
                                                <div>
                                                    <p class="!text-[0.8125rem] font-medium">
                                                        English
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="ti-dropdown-item !p-[0.65rem]">
                                            <div class="flex items-center space-x-2 rtl:space-x-reverse w-full">
                                                <div class="h-[1.375rem] w-[1.375rem] flex items-center rounded-full">
                                                    <img src="/assets/admin/images/flags/spain_flag.jpg"
                                                        alt="flag-img" class="h-[1rem] w-[1rem] rounded-full">
                                                </div>
                                                <div>
                                                    <p class="!text-[0.8125rem] font-medium">
                                                        Spanish
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="ti-dropdown-item !p-[0.65rem]">
                                            <div class="flex items-center space-x-2 rtl:space-x-reverse w-full">
                                                <div class="h-[1.375rem] w-[1.375rem] flex items-center rounded-full">
                                                    <img src="/assets/admin/images/flags/french_flag.jpg"
                                                        alt="flag-img" class="h-[1rem] w-[1rem] rounded-full">
                                                </div>
                                                <div>
                                                    <p class="!text-[0.8125rem] font-medium">
                                                        French
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="ti-dropdown-item !p-[0.65rem]">
                                            <div class="flex items-center space-x-2 rtl:space-x-reverse w-full">
                                                <div class="h-[1.375rem] w-[1.375rem] flex items-center rounded-full">
                                                    <img src="/assets/admin/images/flags/germany_flag.jpg"
                                                        alt="flag-img" class="h-[1rem] w-[1rem] rounded-full">
                                                </div>
                                                <div>
                                                    <p class="!text-[0.8125rem] font-medium">
                                                        German
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="ti-dropdown-item !p-[0.65rem]">
                                            <div class="flex items-center space-x-2 rtl:space-x-reverse w-full">
                                                <div class="h-[1.375rem] w-[1.375rem] flex items-center rounded-full">
                                                    <img src="/assets/admin/images/flags/italy_flag.jpg"
                                                        alt="flag-img" class="h-[1rem] w-[1rem] rounded-full">
                                                </div>
                                                <div>
                                                    <p class="!text-[0.8125rem] font-medium">
                                                        Italian
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="ti-dropdown-item !p-[0.65rem]">
                                            <div class="flex items-center space-x-2 rtl:space-x-reverse w-full">
                                                <div class="h-[1.375rem] w-[1.375rem] flex items-center  rounded-sm">
                                                    <img src="/assets/admin/images/flags/russia_flag.jpg"
                                                        alt="flag-img" class="h-[1rem] w-[1rem] rounded-full">
                                                </div>
                                                <div>
                                                    <p class="!text-[0.8125rem] font-medium">
                                                        Russian
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div> --}}
                        <!-- end header country -->

                        <!-- light and dark theme -->
                        <div
                            class="header-element header-theme-mode hidden !items-center sm:block !py-[1rem] md:!px-[0.65rem] px-2">
                            <a aria-label="anchor"
                                class="hs-dark-mode-active:hidden flex hs-dark-mode group flex-shrink-0 justify-center items-center gap-2  rounded-full font-medium transition-all text-xs dark:bg-bgdark dark:hover:bg-black/20 dark:text-[#8c9097] dark:text-white/50 dark:hover:text-white dark:focus:ring-white/10 dark:focus:ring-offset-white/10"
                                href="javascript:void(0);" data-hs-theme-click-value="dark">
                                <i class="bx bx-moon header-link-icon"></i>
                            </a>
                            <a aria-label="anchor"
                                class="hs-dark-mode-active:flex hidden hs-dark-mode group flex-shrink-0 justify-center items-center gap-2  rounded-full font-medium text-defaulttextcolor  transition-all text-xs dark:bg-bodybg dark:bg-bgdark dark:hover:bg-black/20 dark:text-[#8c9097] dark:text-white/50 dark:hover:text-white dark:focus:ring-white/10 dark:focus:ring-offset-white/10"
                                href="javascript:void(0);" data-hs-theme-click-value="light">
                                <i class="bx bx-sun header-link-icon"></i>
                            </a>
                        </div>
                        <!-- End light and dark theme -->
                        <!--Header Notifictaion -->
                        {{-- <div
                            class="header-element py-[1rem] md:px-[0.65rem] px-2 notifications-dropdown header-notification hs-dropdown ti-dropdown !hidden md:!block [--placement:bottom-left]">
                            <button id="dropdown-notification" type="button"
                                class="hs-dropdown-toggle relative ti-dropdown-toggle !p-0 !border-0 flex-shrink-0  !rounded-full !shadow-none align-middle text-xs">
                                <i class="bx bx-bell header-link-icon  text-[1.125rem]"></i>
                                <span class="flex absolute h-5 w-5 -top-[0.25rem] end-0  -me-[0.6rem]">
                                    <span
                                        class="animate-slow-ping absolute inline-flex -top-[2px] -start-[2px] h-full w-full rounded-full bg-secondary/40 opacity-75"></span>
                                    <span
                                        class="relative inline-flex justify-center items-center rounded-full  h-[14.7px] w-[14px] bg-secondary text-[0.625rem] text-white"
                                        id="notification-icon-badge">5</span>
                                </span>
                            </button>
                            <div class="main-header-dropdown !-mt-3 !p-0 hs-dropdown-menu ti-dropdown-menu bg-white !w-[22rem] border-0 border-defaultborder hidden !m-0"
                                aria-labelledby="dropdown-notification">

                                <div
                                    class="ti-dropdown-header !m-0 !p-4 !bg-transparent flex justify-between items-center">
                                    <p
                                        class="mb-0 text-[1.0625rem] text-defaulttextcolor font-semibold dark:text-[#8c9097] dark:text-white/50">
                                        Notifications</p>
                                    <span
                                        class="text-[0.75em] py-[0.25rem/2] px-[0.45rem] font-[600] rounded-sm bg-secondary/10 text-secondary"
                                        id="notifiation-data">5 Unread</span>
                                </div>
                                <div class="dropdown-divider"></div>
                                <ul class="list-none !m-0 !p-0 end-0" id="header-notification-scroll">
                                    <li class="ti-dropdown-item dropdown-item !block">
                                        <div class="flex items-start">
                                            <div class="pe-2">
                                                <span
                                                    class="inline-flex text-primary justify-center items-center !w-[2.5rem] !h-[2.5rem] !leading-[2.5rem] !text-[0.8rem] !bg-primary/10 !rounded-[50%]"><i
                                                        class="ti ti-gift text-[1.125rem]"></i></span>
                                            </div>
                                            <div class="grow flex items-center justify-between">
                                                <div>
                                                    <p
                                                        class="mb-0 text-defaulttextcolor dark:text-white text-[0.8125rem] font-semibold">
                                                        <a href="javascript:void(0)">Your Order Has Been Shipped</a>
                                                    </p>
                                                    <span
                                                        class="text-[#8c9097] dark:text-white/50 font-normal text-[0.75rem] header-notification-text">Order
                                                        No: 123456
                                                        Has Shipped To Your Delivery Address</span>
                                                </div>
                                                <div>
                                                    <a aria-label="anchor" href="javascript:void(0);"
                                                        class="min-w-fit text-[#8c9097] dark:text-white/50 me-1 dropdown-item-close1"><i
                                                            class="ti ti-x text-[1rem]"></i></a>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                    <li class="ti-dropdown-item dropdown-item !block">
                                        <div class="flex items-start">
                                            <div class="pe-2">
                                                <span
                                                    class="inline-flex text-secondary justify-center items-center !w-[2.5rem] !h-[2.5rem] !leading-[2.5rem] !text-[0.8rem]  bg-secondary/10 rounded-[50%]"><i
                                                        class="ti ti-discount-2 text-[1.125rem]"></i></span>
                                            </div>
                                            <div class="grow flex items-center justify-between">
                                                <div>
                                                    <p
                                                        class="mb-0 text-defaulttextcolor dark:text-white text-[0.8125rem]  font-semibold">
                                                        <a href="javascript:void(0)">Discount Available</a>
                                                    </p>
                                                    <span
                                                        class="text-[#8c9097] dark:text-white/50 font-normal text-[0.75rem] header-notification-text">Discount
                                                        Available On Selected Products</span>
                                                </div>
                                                <div>
                                                    <a aria-label="anchor" href="javascript:void(0);"
                                                        class="min-w-fit  text-[#8c9097] dark:text-white/50 me-1 dropdown-item-close1"><i
                                                            class="ti ti-x text-[1rem]"></i></a>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                    <li class="ti-dropdown-item dropdown-item !block">
                                        <div class="flex items-start">
                                            <div class="pe-2">
                                                <span
                                                    class="inline-flex text-pinkmain justify-center items-center !w-[2.5rem] !h-[2.5rem] !leading-[2.5rem] !text-[0.8rem]  bg-pinkmain/10 rounded-[50%]"><i
                                                        class="ti ti-user-check text-[1.125rem]"></i></span>
                                            </div>
                                            <div class="grow flex items-center justify-between">
                                                <div>
                                                    <p
                                                        class="mb-0 text-defaulttextcolor dark:text-white text-[0.8125rem]  font-semibold">
                                                        <a href="javascript:void(0)">Account Has Been Verified</a>
                                                    </p>
                                                    <span
                                                        class="text-[#8c9097] dark:text-white/50 font-normal text-[0.75rem] header-notification-text">Your
                                                        Account Has
                                                        Been Verified Sucessfully</span>
                                                </div>
                                                <div>
                                                    <a aria-label="anchor" href="javascript:void(0);"
                                                        class="min-w-fit text-[#8c9097] dark:text-white/50 me-1 dropdown-item-close1"><i
                                                            class="ti ti-x text-[1rem]"></i></a>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                    <li class="ti-dropdown-item dropdown-item !block">
                                        <div class="flex items-start">
                                            <div class="pe-2">
                                                <span
                                                    class="inline-flex text-warning justify-center items-center !w-[2.5rem] !h-[2.5rem] !leading-[2.5rem] !text-[0.8rem]  bg-warning/10 rounded-[50%]"><i
                                                        class="ti ti-circle-check text-[1.125rem]"></i></span>
                                            </div>
                                            <div class="grow flex items-center justify-between">
                                                <div>
                                                    <p
                                                        class="mb-0 text-defaulttextcolor dark:text-white text-[0.8125rem]  font-semibold">
                                                        <a href="javascript:void(0)">Order Placed <span
                                                                class="text-warning">ID: #1116773</span></a>
                                                    </p>
                                                    <span
                                                        class="text-[#8c9097] dark:text-white/50 font-normal text-[0.75rem] header-notification-text">Order
                                                        Placed
                                                        Successfully</span>
                                                </div>
                                                <div>
                                                    <a aria-label="anchor" href="javascript:void(0);"
                                                        class="min-w-fit text-[#8c9097] dark:text-white/50 me-1 dropdown-item-close1"><i
                                                            class="ti ti-x text-[1rem]"></i></a>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                    <li class="ti-dropdown-item dropdown-item !block">
                                        <div class="flex items-start">
                                            <div class="pe-2">
                                                <span
                                                    class="inline-flex text-success justify-center items-center !w-[2.5rem] !h-[2.5rem] !leading-[2.5rem] !text-[0.8rem]  bg-success/10 rounded-[50%]"><i
                                                        class="ti ti-clock text-[1.125rem]"></i></span>
                                            </div>
                                            <div class="grow flex items-center justify-between">
                                                <div>
                                                    <p
                                                        class="mb-0 text-defaulttextcolor dark:text-white  text-[0.8125rem]  font-semibold">
                                                        <a href="javascript:void(0)">Order Delayed <span
                                                                class="text-success">ID: 7731116</span></a>
                                                    </p>
                                                    <span
                                                        class="text-[#8c9097] dark:text-white/50 font-normal text-[0.75rem] header-notification-text">Order
                                                        Delayed
                                                        Unfortunately</span>
                                                </div>
                                                <div>
                                                    <a aria-label="anchor" href="javascript:void(0);"
                                                        class="min-w-fit text-[#8c9097] dark:text-white/50 me-1 dropdown-item-close1"><i
                                                            class="ti ti-x text-[1rem]"></i></a>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                </ul>

                                <div class="p-4 empty-header-item1 border-t mt-2">
                                    <div class="grid">
                                        <a href="javascript:void(0)"
                                            class="ti-btn ti-btn-primary-full !m-0 w-full p-2">View All</a>
                                    </div>
                                </div>
                                <div class="p-[3rem] empty-item1 hidden">
                                    <div class="text-center">
                                        <span
                                            class="!h-[4rem]  !w-[4rem] avatar !leading-[4rem] !rounded-full !bg-secondary/10 !text-secondary">
                                            <i class="ri-notification-off-line text-[2rem]  "></i>
                                        </span>
                                        <h6
                                            class="font-semibold mt-3 text-defaulttextcolor dark:text-white text-[1rem]">
                                            No New Notifications</h6>
                                    </div>
                                </div>
                            </div>
                        </div> --}}
                        <!--End Header Notifictaion -->
                        <!-- Fullscreen -->
                        {{-- <div class="header-element header-fullscreen py-[1rem] md:px-[0.65rem] px-2">
                            <!-- Start::header-link -->
                            <a aria-label="anchor" onclick="openFullscreen();" href="javascript:void(0);"
                                class="inline-flex flex-shrink-0 justify-center items-center gap-2  !rounded-full font-medium dark:hover:bg-black/20 dark:text-[#8c9097] dark:text-white/50 dark:hover:text-white dark:focus:ring-white/10 dark:focus:ring-offset-white/10">
                                <i class="bx bx-fullscreen full-screen-open header-link-icon"></i>
                                <i class="bx bx-exit-fullscreen full-screen-close header-link-icon hidden"></i>
                            </a>
                            <!-- End::header-link -->
                        </div> --}}
                        <!-- End Full screen -->

                        @php
                            $user = Auth::user();
                            $userType = $user->userType->user_type;
                        @endphp
                        <!-- Header Profile -->
                        <div
                            class="header-element md:!px-[0.65rem] px-2 hs-dropdown !items-center ti-dropdown [--placement:bottom-left]">

                            <button id="dropdown-profile" type="button"
                                class="hs-dropdown-toggle ti-dropdown-toggle !gap-2 !p-0 flex-shrink-0 sm:me-2 me-0 !rounded-full !shadow-none text-xs align-middle !border-0 !shadow-transparent ">
                                <img class="inline-block rounded-full " src="/assets/admin/images/faces/9.jpg"
                                    width="32" height="32" alt="Image Description">
                            </button>
                            <div class="md:block hidden dropdown-profile">
                                <p class="font-semibold mb-0 leading-none text-[#536485] text-[0.813rem] ">
                                    {{ $user->name }}
                                </p>
                                <span
                                    class="opacity-[0.7] font-normal text-[#536485] block text-[0.6875rem] ">{{ $userType }}</span>
                            </div>
                            <div class="hs-dropdown-menu ti-dropdown-menu !-mt-3 border-0 w-[11rem] !p-0 border-defaultborder hidden main-header-dropdown  pt-0 overflow-hidden header-profile-dropdown dropdown-menu-end"
                                aria-labelledby="dropdown-profile">

                                <ul class="text-defaulttextcolor font-medium dark:text-[#8c9097] dark:text-white/50">
                                    <li>
                                        <a class="w-full ti-dropdown-item !text-[0.8125rem] !gap-x-0  !p-[0.65rem] !inline-flex"
                                            href="javascript:void(0)">
                                            <i class="ti ti-user-circle text-[1.125rem] me-2 opacity-[0.7]"></i>Profile
                                        </a>
                                    </li>
                                    <li>
                                        <a class="w-full ti-dropdown-item !text-[0.8125rem] !gap-x-0 !p-[0.65rem] !inline-flex"
                                            href="{{ route('password.change') }}">
                                            <i class="ti ti-lock text-[1.125rem] me-2 opacity-[0.7]"></i>Change
                                            Password
                                        </a>
                                    </li>
                                    <li><a class="w-full ti-dropdown-item !text-[0.8125rem] !gap-x-0 !p-[0.65rem] !inline-flex"
                                            href="javascript:void(0)"><i
                                                class="ti ti-adjustments-horizontal text-[1.125rem] me-2 opacity-[0.7]"></i>Settings</a>
                                    </li>
                                    <li>
                                        <form method="POST" action="{{ route('logout') }}">
                                            @csrf
                                            <button type="submit"
                                                class="w-full ti-dropdown-item !text-[0.8125rem] !p-[0.65rem] !gap-x-0 !inline-flex"
                                                style="border: none; background: none; cursor: pointer; text-align: left; width: 100%;">
                                                <i class="ti ti-logout text-[1.125rem] me-2 opacity-[0.7]"></i>Log Out
                                            </button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <!-- End Header Profile -->

                        <!-- Switcher Icon -->
                        {{-- <div class="header-element md:px-[0.48rem]">
                            <button aria-label="button" type="button"
                                class="hs-dropdown-toggle switcher-icon inline-flex flex-shrink-0 justify-center items-center gap-2  rounded-full font-medium  align-middle transition-all text-xs dark:text-[#8c9097] dark:text-white/50 dark:hover:text-white dark:focus:ring-white/10 dark:focus:ring-offset-white/10"
                                data-hs-overlay="#hs-overlay-switcher">
                                <i class="bx bx-cog header-link-icon animate-spin-slow"></i>
                            </button>
                        </div> --}}
                        <!-- Switcher Icon -->

                        <!-- End::header-element -->
                    </div>
                </div>
            </nav>
        </header>
        <!-- End::Header -->
        <!-- Start::app-sidebar -->
        <aside class="app-sidebar" id="sidebar">

            <!-- Start::main-sidebar-header -->
            <div class="main-sidebar-header">
                <a href="javascript:void(0)" class="header-logo">
                    <img src="/assets/admin/images/logo.png" alt="logo" class="desktop-logo">
                    <img src="/assets/admin/images/logo.png" alt="logo" class="toggle-logo">
                    <img src="/assets/admin/images/logo.png" alt="logo" class="desktop-dark">
                    <img src="/assets/admin/images/brand-logos/toggle-dark.png" alt="logo" class="toggle-dark">
                    <img src="/assets/admin/images/brand-logos/desktop-white.png" alt="logo"
                        class="desktop-white">
                    <img src="/assets/admin/images/brand-logos/toggle-white.png" alt="logo" class="toggle-white">
                </a>
            </div>
            <!-- End::main-sidebar-header -->

            <!-- Start::main-sidebar -->
            <div class="main-sidebar" id="sidebar-scroll">

                <!-- Start::nav -->
                <nav class="main-menu-container nav nav-pills flex-column sub-open">
                    <div class="slide-left" id="slide-left">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="#7b8191" width="24" height="24"
                            viewBox="0 0 24 24">
                            <path d="M13.293 6.293 7.586 12l5.707 5.707 1.414-1.414L10.414 12l4.293-4.293z"></path>
                        </svg>
                    </div>
                    @php
                        use App\Models\UserType;

                        $accountRoles = UserType::ACCOUNTS_ROLES; // Accounts
                        $operationsRoles = UserType::OPERATIONS_ROLES; // Operations (includes exec)
                        // Roles specifically for operations managers (exclude executives)
                        $operationsManagerRoles = [
                            UserType::SENIOR_OPERATIONS_MANAGER,
                            UserType::OPERATIONS_MANAGER,
                        ];
                        $salesRoles = UserType::SALES_ROLES; // Sales
                        $adminRoles = UserType::ADMIN_ROLES; // Admins

                        function canAccess($userType, $allowedRoles)
                        {
                            return $userType && in_array($userType, $allowedRoles);
                        }
                    @endphp
                    <ul class="main-menu">
                        <!-- Start::slide__category -->
                        <li class="slide__category"><span class="category-name">Main</span></li>
                        <!-- End::slide__category -->

                        <!-- Start::slide -->
                        <!-- @if (canAccess($userType, $adminRoles) || canAccess($userType, $salesRoles))
                            <li class="slide has-sub">
                                <a href="javascript:void(0);" class="side-menu__item">
                                    <i class="bx bx-home side-menu__icon"></i>
                                    <span class="side-menu__label">Dashboards</span>
                                </a>
                                <ul class="slide-menu child1">
                                    <li class="slide side-menu__label1">
                                        <a href="javascript:void(0)">Dashboards</a>
                                    </li>
                                </ul>
                            </li>
                        @endif -->

                        <!-- Sales Dashboard Section -->
                        @if (canAccess($userType, $adminRoles) || canAccess($userType, $salesRoles))
                            <li class="slide {{ Route::is('admin.sales-dashboard') ? 'active' : '' }}">
                                <a href="{{ route('admin.sales-dashboard') }}" class="side-menu__item">
                                    <i class="bx bx-bar-chart side-menu__icon"></i>
                                    <span class="side-menu__label">Sales Dashboard</span>
                                </a>
                            </li>
                        @endif
                        <!-- End::slide -->

                        <!-- Upcoming Rides Section -->
                        @if (canAccess($userType, $adminRoles) || canAccess($userType, $operationsRoles) || canAccess($userType, $salesRoles))
                            <li class="slide {{ Route::is('admin.rides.upcoming') ? 'active' : '' }}">
                                <a href="{{ route('admin.rides.upcoming') }}" class="side-menu__item">
                                    <i class="bx bx-calendar side-menu__icon"></i>
                                    <span class="side-menu__label">Upcoming Rides</span>
                                </a>
                            </li>
                        @endif

                        <!-- Ride Status -->
                        @if (canAccess($userType, $adminRoles) || canAccess($userType, $accountRoles) || canAccess($userType, $operationsRoles))
                            <li class="slide {{ Route::is('admin.rides.ride-status') ? 'active' : '' }}">
                                <a href="{{ route('admin.rides.ride-status') }}" class="side-menu__item">
                                    <i class="bx bx-list-check side-menu__icon"></i>
                                    <span class="side-menu__label">Ride Status</span>
                                </a>
                            </li>
                        @endif

                        <!-- Refund Notes -->
                        @if (canAccess($userType, $adminRoles) || canAccess($userType, $accountRoles) || canAccess($userType, $operationsRoles))
                            <li class="slide {{ Route::is('admin.refunds.*') ? 'active' : '' }}">
                                <a href="{{ route('admin.refunds.index') }}" class="side-menu__item">
                                    <i class="bx bx-undo side-menu__icon"></i>
                                    <span class="side-menu__label">Refund Notes</span>
                                </a>
                            </li>
                        @endif

                        <!-- Start::slide__category -->
                        <li class="slide__category"><span class="category-name">Pages</span></li>
                        <!-- End::slide__category -->

                        <!-- Client Section -->
                        @if (canAccess($userType, $adminRoles) || canAccess($userType, $salesRoles))
                            <li class="slide has-sub {{ Route::is('admin.client.*') ? 'active open' : '' }}">
                                <a href="javascript:void(0);" class="side-menu__item">
                                    <i class="bx bx-user side-menu__icon"></i>
                                    <span class="side-menu__label">Client</span>
                                    <i class="fe fe-chevron-right side-menu__angle"></i>
                                </a>
                                <ul class="slide-menu child1">
                                    <li class="slide side-menu__label1"><a href="javascript:void(0)">Pages</a></li>
                                    <!-- <li class="slide">
                                    <a href="{{ route('admin.client.create') }}"
                                        class="side-menu__item {{ Route::is('admin.client.create') ? 'active' : '' }}">
                                        Add Client
                                    </a>
                                </li> -->
                                    <li class="slide">
                                        <a href="{{ route('admin.client.index') }}" class="side-menu__item">View
                                            Client</a>
                                    </li>
                                </ul>
                            </li>
                        @endif

                        <!-- Leads Section -->
                        @if (canAccess($userType, $adminRoles) || canAccess($userType, $operationsRoles) || canAccess($userType, $salesRoles))
                            <li class="slide has-sub {{ Route::is('admin.clients.*') ? 'active open' : '' }}">
                                <a href="javascript:void(0);" class="side-menu__item">
                                    <i class="bx bx-trending-up side-menu__icon"></i>
                                    <span class="side-menu__label">Leads</span>
                                    <i class="fe fe-chevron-right side-menu__angle"></i>
                                </a>
                                <ul class="slide-menu child1">
                                    <li class="slide side-menu__label1"><a href="javascript:void(0)">Pages</a></li>
                                    <li class="slide">
                                        <a href="{{ route('admin.clients.create') }}"
                                            class="side-menu__item {{ Route::is('admin.clients.create') ? 'active' : '' }}">
                                            Add Lead
                                        </a>
                                    </li>
                                    <li class="slide">
                                        <a href="{{ route('admin.clients.index') }}" class="side-menu__item">View
                                            Leads</a>
                                    </li>
                                    <li class="slide">
                                        <a href="{{ route('admin.leads.dnp') }}" class="side-menu__item">View DNP
                                            Leads</a>
                                    </li>
                                </ul>
                            </li>
                        @endif

                        <!-- Lead Tracking Section -->
                        @if (canAccess($userType, $adminRoles) || canAccess($userType, $accountRoles))
                            <li class="slide {{ Route::is('admin.lead-tracking.*') ? 'active' : '' }}">
                                <a href="{{ route('admin.lead-tracking.index') }}" class="side-menu__item">
                                    <i class="bx bx-search-alt side-menu__icon"></i>
                                    <span class="side-menu__label">Lead Tracking</span>
                                </a>
                            </li>
                        @endif
                        
                        <!-- Voucher Generation Direct Link -->
                        @if (canAccess($userType, $adminRoles) || canAccess($userType, $operationsRoles))
                            <li class="slide {{ Route::is('admin.vouchers.index') ? 'active' : '' }}">
                                <a href="{{ route('admin.vouchers.index') }}" class="side-menu__item">
                                    <i class="bx bx-file side-menu__icon"></i>
                                    <span class="side-menu__label">Voucher Generation</span>
                                </a>
                            </li>
                        @endif

                        <!-- Staff Section -->
                        @if (canAccess($userType, $adminRoles))
                            <li class="slide has-sub {{ Route::is('admin.users.*') ? 'active open' : '' }}">
                                <a href="javascript:void(0);" class="side-menu__item">
                                    <i class="bx bx-user-pin side-menu__icon"></i>
                                    <span class="side-menu__label">Staff</span>
                                    <i class="fe fe-chevron-right side-menu__angle"></i>
                                </a>
                                <ul class="slide-menu child1">
                                    <li class="slide side-menu__label1"><a href="javascript:void(0)">Pages</a></li>
                                    <!-- <li class="slide">
                                    <a href="{{ route('admin.users.create') }}"
                                        class="side-menu__item {{ Route::is('admin.users.create') ? 'active' : '' }}">
                                        Add Staff
                                    </a>
                                </li> -->
                                    <li class="slide">
                                        <a href="{{ route('admin.users.index') }}" class="side-menu__item">View
                                            Staff</a>
                                    </li>
                                </ul>
                            </li>
                        @endif

                        <!-- Target Master Section -->
                        @if (canAccess($userType, $adminRoles) ||
                                in_array($userType, [\App\Models\UserType::SENIOR_SALES_MANAGER, \App\Models\UserType::SALES_MANAGER]))
                            <li class="slide has-sub {{ Route::is('admin.targets.*') ? 'active open' : '' }}">
                                <a href="javascript:void(0);" class="side-menu__item">
                                    <i class="bx bx-target-lock side-menu__icon"></i>
                                    <span class="side-menu__label">Target Master</span>
                                    <i class="fe fe-chevron-right side-menu__angle"></i>
                                </a>
                                <ul class="slide-menu child1">
                                    <li class="slide side-menu__label1"><a href="javascript:void(0)">Pages</a></li>
                                    <li class="slide">
                                        <a href="{{ route('admin.targets.index') }}" class="side-menu__item">View
                                            Targets</a>
                                    </li>
                                </ul>
                            </li>
                        @endif

                        <!-- Voucher Generation Direct Link -->
                        @if (canAccess($userType, $adminRoles) || canAccess($userType, $operationsRoles))
                            <li class="slide {{ Route::is('admin.notification-master.index') ? 'active' : '' }}">
                                <a href="{{ route('admin.notification-master.index') }}" class="side-menu__item">
                                    <i class="bx bx-file side-menu__icon"></i>
                                    <span class="side-menu__label">Notification Master</span>
                                </a>
                            </li>
                        @endif

                        <!-- Sales Executive Management Section -->
                        @if (canAccess($userType, $adminRoles) ||
                                in_array($userType, [\App\Models\UserType::SENIOR_SALES_MANAGER, \App\Models\UserType::SALES_MANAGER]))
                            <li class="slide has-sub {{ Route::is('admin.sales-executive-management.*') ? 'active open' : '' }}">
                                <a href="javascript:void(0);" class="side-menu__item">
                                    <i class="bx bx-user-check side-menu__icon"></i>
                                    <span class="side-menu__label">Assign Sales Executive</span>
                                    <i class="fe fe-chevron-right side-menu__angle"></i>
                                </a>
                                <ul class="slide-menu child1">
                                    <li class="slide side-menu__label1"><a href="javascript:void(0)">Management</a></li>
                                    <li class="slide">
                                        <a href="{{ route('admin.sales-executive-management.index') }}" class="side-menu__item">Manage
                                            Sales Executive</a>
                                    </li>
                                </ul>
                            </li>
                        @endif

                        <!-- Services Section -->
                        @if (canAccess($userType, $adminRoles) || canAccess($userType, $operationsManagerRoles))
                            <li class="slide has-sub {{ Route::is('admin.services.*') ? 'active open' : '' }}">
                                <a href="javascript:void(0);" class="side-menu__item">
                                    <i class="bx bx-cog side-menu__icon"></i>
                                    <span class="side-menu__label">Services</span>
                                    <i class="fe fe-chevron-right side-menu__angle"></i>
                                </a>
                                <ul class="slide-menu child1">
                                    <li class="slide side-menu__label1"><a href="javascript:void(0)">Pages</a></li>
                                    <li class="slide">
                                        <a href="{{ route('admin.services.create') }}"
                                            class="side-menu__item {{ Route::is('admin.services.create') ? 'active' : '' }}">
                                            Add Service
                                        </a>
                                    </li>
                                    <li class="slide">
                                        <a href="{{ route('admin.services.index') }}" class="side-menu__item">View
                                            Services</a>
                                    </li>
                                </ul>
                            </li>
                        @endif

                        <!-- Extra Services -->
                        @if (canAccess($userType, $adminRoles))
                            <li class="slide has-sub {{ Route::is('admin.extra-services.*') ? 'active open' : '' }}">
                                <a href="javascript:void(0);" class="side-menu__item">
                                    <i class="bx bx-cog side-menu__icon"></i>
                                    <span class="side-menu__label">Extra Services</span>
                                    <i class="fe fe-chevron-right side-menu__angle"></i>
                                </a>
                                <ul class="slide-menu child1">
                                    <li class="slide side-menu__label1"><a href="javascript:void(0)">Pages</a></li>
                                    <li class="slide">
                                        <a href="{{ route('admin.extra-services.index') }}"
                                            class="side-menu__item {{ Route::is('admin.extra-services.index') ? 'active' : '' }}">
                                            View Extra Services
                                        </a>
                                    </li>
                                </ul>
                            </li>
                        @endif

                        <!-- Products Section -->
                        @if (canAccess($userType, $adminRoles) || canAccess($userType, $operationsManagerRoles))
                            <li class="slide has-sub {{ Route::is('admin.products.*') ? 'active open' : '' }}">
                                <a href="javascript:void(0);" class="side-menu__item">
                                    <i class="bx bx-package side-menu__icon"></i>
                                    <span class="side-menu__label">Products</span>
                                    <i class="fe fe-chevron-right side-menu__angle"></i>
                                </a>
                                <ul class="slide-menu child1">
                                    <li class="slide side-menu__label1"><a href="javascript:void(0)">Pages</a></li>
                                    <!-- <li class="slide">
                                    <a href="{{ route('admin.products.create') }}"
                                        class="side-menu__item {{ Route::is('admin.products.create') ? 'active' : '' }}">
                                        Add Product
                                    </a>
                                </li> -->
                                    <li class="slide">
                                        <a href="{{ route('admin.products.index') }}" class="side-menu__item">View
                                            Products</a>
                                    </li>
                                    <li class="slide">
                                        <a href="{{ route('admin.product-sync.index') }}" class="side-menu__item {{ Route::is('admin.product-sync.*') ? 'active' : '' }}">Product Sync (Airpoints)</a>
                                    </li>
                                </ul>
                            </li>
                        @endif

                        <!-- Services Section -->
                        @if (canAccess($userType, $adminRoles) || canAccess($userType, $operationsManagerRoles))
                            <li
                                class="slide has-sub {{ Route::is('admin.service-addresses.*') ? 'active open' : '' }}">
                                <a href="javascript:void(0);" class="side-menu__item">
                                    <i class="bx bx-cog side-menu__icon"></i>
                                    <span class="side-menu__label">Service Addresses</span>
                                    <i class="fe fe-chevron-right side-menu__angle"></i>
                                </a>
                                <ul class="slide-menu child1">
                                    <li class="slide side-menu__label1"><a href="javascript:void(0)">Pages</a></li>
                                    <!-- <li class="slide">
                                    <a href="{{ route('admin.service-addresses.create') }}"
                                        class="side-menu__item {{ Route::is('admin.service-addresses.create') ? 'active' : '' }}">
                                        Add Service Address
                                    </a>
                                </li> -->
                                    <li class="slide">
                                        <a href="{{ route('admin.service-addresses.index') }}"
                                            class="side-menu__item">View Service Addresses</a>
                                    </li>
                                </ul>
                            </li>
                        @endif

                        <!-- Vendors Section -->
                        @if (canAccess($userType, $adminRoles) || canAccess($userType, $operationsRoles) || canAccess($userType, $accountRoles))
                            <li class="slide has-sub {{ Route::is('admin.vendors.*') ? 'active open' : '' }}">
                                <a href="javascript:void(0);" class="side-menu__item">
                                    <i class="bx bx-store side-menu__icon"></i>
                                    <span class="side-menu__label">Vendors</span>
                                    <i class="fe fe-chevron-right side-menu__angle"></i>
                                </a>
                                <ul class="slide-menu child1">
                                    <li class="slide side-menu__label1"><a href="javascript:void(0)">Pages</a></li>
                                    <!-- <li class="slide">
                                    <a href="{{ route('admin.vendors.create') }}"
                                        class="side-menu__item {{ Route::is('admin.vendors.create') ? 'active' : '' }}">
                                        Add Vendor
                                    </a>
                                </li> -->
                                    <li class="slide">
                                        <a href="{{ route('admin.vendors.index') }}"
                                            class="side-menu__item {{ Route::is('admin.vendors.index') ? 'active' : '' }}">
                                            View Vendors
                                        </a>
                                    </li>
                                </ul>
                            </li>
                        @endif

                        <!-- User Roles Direct Link -->
                        @if (canAccess($userType, $adminRoles))
                            <li class="slide {{ Route::is('admin.user-types.*') ? 'active' : '' }}">
                                <a href="{{ route('admin.user-types.create') }}" class="side-menu__item">
                                    <i class="bx bx-shield-alt side-menu__icon"></i>
                                    <span class="side-menu__label">User Roles</span>
                                </a>
                            </li>
                        @endif

                        <!-- Follow Up Status Direct Link -->
                        @if (canAccess($userType, $adminRoles) || canAccess($userType, $salesRoles))
                            <li class="slide {{ Route::is('admin.upcoming-follow-up.*') ? 'active' : '' }}">
                                <a href="{{ route('admin.upcoming-follow-up.index') }}" class="side-menu__item">
                                    <i class="bx bx-task side-menu__icon"></i>
                                    <span class="side-menu__label">Today's Follow-up</span>
                                </a>
                            </li>
                        @endif

                        <li class="slide {{ Route::is('admin.report') ? 'active' : '' }}">
                            <a href="{{ route('admin.report') }}" class="side-menu__item">
                                <i class="bx bx-task side-menu__icon"></i>
                                <span class="side-menu__label">Reports</span>
                            </a>
                        </li>
                        @if(canAccess($userType, $adminRoles) || canAccess($userType, $accountRoles))
                            <!-- Accounts Reports -->
                            <li class="slide has-sub {{ Route::is('admin.report.sales') || Route::is('admin.report.vendor') || Route::is('admin.report.profit-loss') ? 'open active' : '' }}">
                                <a href="javascript:void(0);" class="side-menu__item">
                                    <i class="bx bx-file side-menu__icon"></i>
                                    <span class="side-menu__label">Accounts Reports</span>
                                    <i class="fe fe-chevron-right side-menu__angle"></i>
                                </a>
                                <ul class="slide-menu child1">
                                    <li class="slide {{ Route::is('admin.report.sales') ? 'active' : '' }}">
                                        <a href="{{ route('admin.report.sales') }}" class="side-menu__item">Sales Report</a>
                                    </li>
                                    <li class="slide {{ Route::is('admin.report.vendor') ? 'active' : '' }}">
                                        <a href="{{ route('admin.report.vendor') }}" class="side-menu__item">Vendor Report</a>
                                    </li>
                                    <li class="slide {{ Route::is('admin.report.profit-loss') ? 'active' : '' }}">
                                        <a href="{{ route('admin.report.profit-loss') }}" class="side-menu__item">Profit/Loss Report</a>
                                    </li>
                                    <li class="slide {{ Route::is('admin.report.kpi') ? 'active' : '' }}">
                                        <a href="{{ route('admin.report.kpi') }}" class="side-menu__item">KPI Report</a>
                                    </li>
                                </ul>
                            </li>
                        @endif
                        <!-- Payment Review Direct Link -->
                        @if (canAccess($userType, $adminRoles) || canAccess($userType, $accountRoles) || canAccess($userType, $operationsManagerRoles))
                            <li class="slide {{ Route::is('admin.account.payment-review*') ? 'active' : '' }}">
                                <a href="{{ route('admin.account.payment-review') }}" class="side-menu__item">
                                    <i class="bx bx-wallet side-menu__icon"></i>
                                    <span class="side-menu__label">Payment Review</span>
                                </a>
                            </li>
                            <li class="slide {{ Route::is('admin.account.exceptional*') ? 'active' : '' }}">
                                <a href="{{ route('admin.account.exceptional') }}" class="side-menu__item">
                                    <i class="bx bx-error-alt side-menu__icon"></i>
                                    <span class="side-menu__label">Exceptional Dashboard</span>
                                </a>
                            </li>
                        @endif

                        <!-- Vendor Payments Direct Link -->
                        @if (canAccess($userType, $adminRoles) || canAccess($userType, $accountRoles) || canAccess($userType, $operationsRoles))
                            <li class="slide {{ Route::is('admin.account.vendor-payments*') ? 'active' : '' }}">
                                <a href="{{ route('admin.account.vendor-payments') }}" class="side-menu__item">
                                    <i class="bx bx-credit-card side-menu__icon"></i>
                                    <span class="side-menu__label">Vendor Payments</span>
                                </a>
                            </li>
                        @endif

                        <!-- Invoices Direct Link -->
                        @if (canAccess($userType, $adminRoles) || canAccess($userType, $accountRoles))
                            <li class="slide {{ Route::is('admin.account.invoices*') ? 'active' : '' }}">
                                <a href="{{ route('admin.account.invoices') }}" class="side-menu__item">
                                    <i class="bx bx-receipt side-menu__icon"></i>
                                    <span class="side-menu__label">Invoices</span>
                                </a>
                            </li>
                        @endif

                        <!-- Refund Notes Direct Link -->
                        <!--@if (canAccess($userType, $adminRoles) || canAccess($userType, $accountRoles))
<li class="slide {{ Route::is('refund-notes.*') ? 'active' : '' }}">
                            <a href="{{ route('admin.refunds.index') }}" class="side-menu__item">
                                <i class="bx bx-money-withdraw side-menu__icon"></i>
                                <span class="side-menu__label">Refund Notes</span>
                            </a>
                        </li>
@endif-->

                        <!-- Leads Section -->
                        @if (canAccess($userType, $adminRoles) || canAccess($userType, $operationsRoles) || canAccess($userType, $salesRoles))
                            <li class="slide has-sub {{ Route::is('admin.leads.*') ? 'active open' : '' }}">
                                <a href="javascript:void(0);" class="side-menu__item">
                                    <i class="bx bx-import side-menu__icon"></i>
                                    <span class="side-menu__label">Import</span>
                                    <i class="fe fe-chevron-right side-menu__angle"></i>
                                </a>
                                <ul class="slide-menu child1">
                                    <li class="slide side-menu__label1"><a href="javascript:void(0)">Pages</a></li>
                                    <li class="slide">
                                        <a href="{{ route('admin.leads.import') }}"
                                            class="side-menu__item {{ Route::is('admin.leads.import*') ? 'active' : '' }}">
                                            Import Leads
                                        </a>
                                    </li>
                                </ul>
                            </li>
                        @endif
                    </ul>
                    <div class="slide-right" id="slide-right">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="#7b8191" width="24" height="24"
                            viewBox="0 0 24 24">
                            <path d="M10.707 17.707 16.414 12l-5.707-5.707-1.414 1.414L13.586 12l-4.293 4.293z"></path>
                        </svg>
                    </div>
                </nav>
                <!-- End::nav -->

            </div>
            <!-- End::main-sidebar -->

        </aside>
        <!-- End::app-sidebar -->


        <div class="content main-index">

            <!-- Start::main-content -->
            <div class="main-content">
                @yield('content')
            </div>
            <!-- end::main-content -->

        </div>

        <!-- ========== Search Modal ========== -->
        <div id="search-modal" class="hs-overlay ti-modal hidden mt-[1.75rem]">
            <div class="ti-modal-box">
                <div
                    class="ti-modal-content !border !border-defaultborder dark:!border-defaultborder/10 !rounded-[0.5rem]">
                    <div class="ti-modal-body">

                        <div class="input-group border-[2px] border-primary rounded-[0.25rem] w-full flex">
                            <a aria-label="anchor" href="javascript:void(0);"
                                class="input-group-text flex items-center bg-light border-e-[#dee2e6] !py-[0.375rem] !px-[0.75rem] !rounded-none !text-[0.875rem]"
                                id="Search-Grid"><i class="fe fe-search header-link-icon text-[0.875rem]"></i></a>

                            <input type="search"
                                class="form-control border-0 px-2 !text-[0.8rem] w-full focus:ring-transparent"
                                placeholder="Search" aria-label="Username">

                            <a aria-label="anchor" href="javascript:void(0);"
                                class="flex items-center input-group-text bg-light !py-[0.375rem] !px-[0.75rem] !border-e-0"
                                id="voice-search"><i class="fe fe-mic header-link-icon"></i></a>
                            <div class="hs-dropdown ti-dropdown">
                                <a aria-label="anchor" href="javascript:void(0);"
                                    class="flex items-center hs-dropdown-toggle ti-dropdown-toggle btn btn-light btn-icon !bg-light !py-[0.375rem] !rounded-none !px-[0.75rem] text-[0.95rem] h-[2.413rem] w-[2.313rem]">
                                    <i class="fe fe-more-vertical"></i>
                                </a>

                                <ul class="absolute hs-dropdown-menu ti-dropdown-menu !-mt-2 !p-0 hidden">
                                    <li><a class="ti-dropdown-item flex text-defaulttextcolor dark:text-defaulttextcolor/70 !py-[0.5rem] !px-[0.9375rem] !text-[0.8125rem] font-medium"
                                            href="javascript:void(0);">Action</a></li>
                                    <li><a class="ti-dropdown-item flex text-defaulttextcolor dark:text-defaulttextcolor/70 !py-[0.5rem] !px-[0.9375rem] !text-[0.8125rem] font-medium"
                                            href="javascript:void(0);">Another action</a></li>
                                    <li><a class="ti-dropdown-item flex text-defaulttextcolor dark:text-defaulttextcolor/70 !py-[0.5rem] !px-[0.9375rem] !text-[0.8125rem] font-medium"
                                            href="javascript:void(0);">Something else here</a></li>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                    <li><a class="ti-dropdown-item flex text-defaulttextcolor dark:text-defaulttextcolor/70 !py-[0.5rem] !px-[0.9375rem] !text-[0.8125rem] font-medium"
                                            href="javascript:void(0);">Separated link</a></li>
                                </ul>
                            </div>
                        </div>
                        <div class="mt-5">
                            <p
                                class="font-normal  text-[#8c9097] dark:text-white/50 text-[0.813rem] dark:text-gray-200 mb-2">
                                Are You Looking For...</p>

                            <span
                                class="search-tags text-[0.75rem] !py-[0rem] !px-[0.55rem] dark:border-defaultborder/10"><i
                                    class="fe fe-user me-2"></i>People<a href="javascript:void(0)"
                                    class="tag-addon header-remove-btn"><span class="sr-only">Remove badge</span><i
                                        class="fe fe-x"></i></a></span>
                            <span
                                class="search-tags text-[0.75rem] !py-[0rem] !px-[0.55rem] dark:border-defaultborder/10"><i
                                    class="fe fe-file-text me-2"></i>Pages<a href="javascript:void(0)"
                                    class="tag-addon header-remove-btn"><span class="sr-only">Remove badge</span><i
                                        class="fe fe-x"></i></a></span>
                            <span
                                class="search-tags text-[0.75rem] !py-[0rem] !px-[0.55rem] dark:border-defaultborder/10"><i
                                    class="fe fe-align-left me-2"></i>Articles<a href="javascript:void(0)"
                                    class="tag-addon header-remove-btn"><span class="sr-only">Remove badge</span><i
                                        class="fe fe-x"></i></a></span>
                            <span
                                class="search-tags text-[0.75rem] !py-[0rem] !px-[0.55rem] dark:border-defaultborder/10"><i
                                    class="fe fe-server me-2"></i>Tags<a href="javascript:void(0)"
                                    class="tag-addon header-remove-btn"><span class="sr-only">Remove badge</span><i
                                        class="fe fe-x"></i></a></span>

                        </div>


                        <div class="my-[1.5rem]">
                            <p class="font-normal  text-[#8c9097] dark:text-white/50 text-[0.813rem] mb-2">Recent
                                Search :</p>

                            <div id="dismiss-alert" role="alert"
                                class="!p-2 border dark:border-defaultborder/10 rounded-[0.3125rem] flex items-center text-defaulttextcolor dark:text-defaulttextcolor/70 !mb-2 !text-[0.8125rem] alert">
                                <a href="javascript:void(0)"><span>Notifications</span></a>
                                <a aria-label="anchor" class="ms-auto leading-none" href="javascript:void(0);"
                                    data-hs-remove-element="#dismiss-alert"><i
                                        class="fe fe-x !text-[0.8125rem] text-[#8c9097] dark:text-white/50"></i></a>
                            </div>

                            <div id="dismiss-alert-1" role="alert"
                                class="!p-2 border dark:border-defaultborder/10 rounded-[0.3125rem] flex items-center text-defaulttextcolor dark:text-defaulttextcolor/70 !mb-2 !text-[0.8125rem] alert">
                                <a href="javascript:void(0)"><span>Alerts</span></a>
                                <a aria-label="anchor" class="ms-auto leading-none" href="javascript:void(0);"
                                    data-hs-remove-element="#dismiss-alert-1"><i
                                        class="fe fe-x !text-[0.8125rem] text-[#8c9097] dark:text-white/50"></i></a>
                            </div>

                            <div id="dismiss-alert-2" role="alert"
                                class="!p-2 border dark:border-defaultborder/10 rounded-[0.3125rem] flex items-center text-defaulttextcolor dark:text-defaulttextcolor/70 !mb-0 !text-[0.8125rem] alert">
                                <a href="javascript:void(0)"><span>Mail</span></a>
                                <a aria-label="anchor" class="ms-auto lh-1" href="javascript:void(0);"
                                    data-hs-remove-element="#dismiss-alert-2"><i
                                        class="fe fe-x !text-[0.8125rem] text-[#8c9097] dark:text-white/50"></i></a>
                            </div>
                        </div>
                    </div>

                    <div class="ti-modal-footer !py-[1rem] !px-[1.25rem]">
                        <div class="inline-flex rounded-md  shadow-sm">
                            <button type="button"
                                class="ti-btn-group !px-[0.75rem] !py-[0.45rem]  rounded-s-[0.25rem] !rounded-e-none ti-btn-primary !text-[0.75rem] dark:border-white/10">
                                Search
                            </button>
                            <button type="button"
                                class="ti-btn-group  ti-btn-primary-full rounded-e-[0.25rem] dark:border-white/10 !text-[0.75rem] !rounded-s-none !px-[0.75rem] !py-[0.45rem]">
                                Clear Recents
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- ========== END Search Modal ========== -->

        @include('admin.layouts.footer')

    </div>

    <!-- Back To Top -->
    <div class="scrollToTop">
        <span class="arrow"><i class="ri-arrow-up-s-fill text-xl"></i></span>
    </div>

    <div id="responsive-overlay"></div>

    <!-- Jquery Cdn -->
    <script src="https://code.jquery.com/jquery-3.6.1.min.js"
        integrity="sha256-o88AwQnZB+VDvE9tvIXrMQaPlFFSUTR+nldQm1LuPXQ=" crossorigin="anonymous"></script>

    <!-- Switch JS -->
    <script src="/assets/admin/js/switch.js"></script>

    <!-- Preline JS -->
    <script src="/assets/admin/libs/preline/preline.js"></script>

    <!-- popperjs -->
    <script src="/assets/admin/libs/@popperjs/core/umd/popper.min.js"></script>

    <!-- Color Picker JS -->
    <script src="/assets/admin/libs/@simonwep/pickr/pickr.es5.min.js"></script>

    <!-- sidebar JS -->
    <script src="/assets/admin/js/defaultmenu.js"></script>

    <!-- sticky JS -->
    <script src="/assets/admin/js/sticky.js"></script>

    <!-- Simplebar JS -->
    <script src="/assets/admin/libs/simplebar/simplebar.min.js"></script>

    <!-- JSVector Maps JS -->
    <script src="\assets\admin\libs\jsvectormap\js\jsvectormap.min.js"></script>

    <!-- JSVector Maps MapsJS -->
    <script src="\assets\admin\libs\jsvectormap\maps\world-merc.js"></script>

    <!-- Apex Charts JS -->
    <script src="\assets\admin\libs\apexcharts\apexcharts.min.js"></script>

    <!-- Chartjs Chart JS -->
    <script src="\assets\admin\libs\chart.js\chart.min.js"></script>

    <!-- Internal Apex Pie Charts JS -->
    <script src="/assets/admin/js/apexcharts-pie.js"></script>

    <!-- CRM-Dashboard -->
    <script src="/assets/admin/js/crm-dashboard.js"></script>

    <!-- Sales-Dashboard JS -->
    <script src="/assets/admin/js/sales-dashboard.js"></script>

    <!-- Custom-Switcher JS -->
    <script src="/assets/admin/js/custom-switcher.js"></script>

    <!-- Date & Time Picker JS -->
    <script src="/assets/admin/libs/flatpickr/flatpickr.min.js"></script>
    <script src="/assets/admin/js/date-time_pickers.js"></script>

    <!-- Quill Editor JS -->
    <script src="/assets/admin/libs/quill/quill.min.js"></script>

    <!-- Internal Quill JS -->
    <script src="/assets/admin/js/quill-editor.js"></script>

    <!-- Moment JS -->
    <script src="/assets/admin/libs/moment/moment.js"></script>

    <!-- Fullcalendar JS -->
    <script src="/assets/admin/libs/fullcalendar/main.min.js"></script>
    <script src="/assets/admin/js/fullcalendar.js"></script>

    <!-- Select2 Cdn -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <!-- Internal Select-2.js -->
    <script src="/assets/admin/js/select2.js"></script>

    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/js/intlTelInput.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const phoneInput = document.querySelector("#phone");
            const whatsappInput = document.querySelector("#whatsapp");

            if (phoneInput) {
                window.intlTelInput(phoneInput, {
                    separateDialCode: false,
                    initialCountry: "in",
                    utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/js/utils.js"
                });
            }

            if (whatsappInput) {
                window.intlTelInput(whatsappInput, {
                    separateDialCode: false,
                    initialCountry: "in",
                    utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/js/utils.js"
                });
            }
        });
    </script>

    <!-- Main JS -->
    <script src="/assets/admin/js/main.js"></script>

    <!-- Custom JS -->
    <script src="/assets/admin/js/custom.js"></script>

    <script>
        $(document).ready(function() {
            const $tables = $('.table-datatable').not('.server-paginated');
            const emptyMsg = $tables.first().data('empty-msg') || 'No records available';
            if ($tables.length && !$.fn.DataTable.isDataTable($tables.first())) {
                $tables.DataTable({
                    responsive: false, // No plus icon
                    scrollX: true, // Horizontal scroll
                    columnDefs: [{
                        orderable: false,
                        targets: 0 // S.No. column — make non-sortable
                    }],
                    language: {
                        emptyTable: emptyMsg, // shown when table has no data at all
                        zeroRecords: emptyMsg // shown when search/filter returns no rows
                    },
                    order: [
                        [0, 'asc']
                    ], // Sort by S.No.
                    drawCallback: function(settings) {
                        var api = this.api();
                        api.rows({
                            page: 'current'
                        }).every(function(rowIdx) {
                            var cell = this.cell(rowIdx, 0)
                                .node(); // Set S.No. in first column (index 0)
                            $(cell).html(rowIdx + 1);
                        });
                    }
                });
            }
        });
    </script>


    <script>
        // document.addEventListener('DOMContentLoaded', function () {
        //   const container = document.getElementById('repeatableFieldsContainer');

        //   container.addEventListener('click', function (e) {
        //     const target = e.target;

        //     // Handle Add Button
        //     if (target.classList.contains('addBtn')) {
        //       const row = target.closest('.repeatable-row');
        //       const clone = row.cloneNode(true);

        //       // Reset values
        //       clone.querySelectorAll('input, textarea').forEach(el => el.value = '');

        //       // Change button to Remove
        //       const button = clone.querySelector('button');
        //       button.textContent = '−';
        //       button.classList.remove('addBtn', 'bg-green-500');
        //       button.classList.add('removeBtn', 'bg-red-500');

        //       container.appendChild(clone);
        //     }

        //     // Handle Remove Button
        //     if (target.classList.contains('removeBtn')) {
        //       const row = target.closest('.repeatable-row');
        //       row.remove();
        //     }
        //   });
        // });
    </script>

    @stack('scripts')
</body>

</html>
