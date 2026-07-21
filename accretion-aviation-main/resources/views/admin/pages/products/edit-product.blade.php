@extends('admin.layouts.header')
@section('content')
    <!-- Page Header -->
    <div class="block justify-between page-header md:flex">
        <div>
            <h3 class="!text-defaulttextcolor dark:!text-defaulttextcolor/70 dark:text-white dark:hover:text-white text-[1.125rem] font-semibold"> Edit Product</h3>
        </div>
        <ol class="flex items-center whitespace-nowrap min-w-0">
            <li class="text-[0.813rem] ps-[0.5rem]">
              <a class="flex items-center text-primary hover:text-primary dark:text-primary truncate" href="{{ route('admin.products.index') }}">
                Products
                <i class="ti ti-chevrons-right flex-shrink-0 text-[#8c9097] dark:text-white/50 px-[0.5rem] overflow-visible rtl:rotate-180"></i>
              </a>
            </li>
            <li class="text-[0.813rem] text-defaulttextcolor font-semibold hover:text-primary dark:text-[#8c9097] dark:text-white/50 " aria-current="page">
                Edit Product
            </li>
        </ol>
    </div>
    <!-- Page Header Close -->

    <div class="grid grid-cols-12 gap-6 text-defaultsize">
        <div class="xl:col-span-12 col-span-12">
            <div class="box">
                <form action="{{ route('admin.products.update', $product->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="box-body">
                        <div class="grid grid-cols-12 sm:gap-6">
                             
                            <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label for="product" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Product Name *</label>
                                <input type="text" name="product" class="ti-form-input rounded-sm form-control-sm" id="product" value="{{ old('product', $product->product) }}" required>
                                @error('product')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label for="is_private" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Private Charter :</label>
                                <input type="hidden" name="is_private" value="0">
                                <input class="form-check-input" type="checkbox" value="1" id="is_private" name="is_private" {{ (old('is_private', $product->is_private ?? false) ? 'checked' : '') }}>
                            </div>
                            <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label for="is_airambulance" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Air Ambulance :</label>
                                <input type="hidden" name="is_airambulance" value="0">
                                <input class="form-check-input" type="checkbox" value="1" id="is_airambulance" name="is_airambulance" {{ (old('is_airambulance', $product->is_airambulance ?? 1) ? 'checked' : '') }}>
                                @error('is_airambulance')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>
                    <div class="box-footer">
                        <button type="submit" class="ti-btn ti-btn-primary-full ti-custom-validate-btn">Update Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            $('.js-example-basic-multiple').select2({
                placeholder: "Select services",
                allowClear: true
            });

            // Initialize the editor
            const editor = document.getElementById('editor');
            if (editor) {
                editor.addEventListener('input', function() {
                    document.getElementById('terms_conditions').value = editor.innerHTML;
                });
            }
        });
    </script>
@endsection