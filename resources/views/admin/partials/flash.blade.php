@if(session('success'))
    <script>
        window.adminToastSuccess = @json(session('success'));
    </script>
@endif

@if($errors->any())
    <script>
        window.adminValidationErrors = @json($errors->all());
    </script>
@endif

@if(session('error'))
    <script>
        window.adminToastError = @json(session('error'));
    </script>
@endif
