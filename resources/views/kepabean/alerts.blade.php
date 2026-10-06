@foreach(['success' => 'success', 'error' => 'danger'] as $key => $class)
    @if(session($key))<div class="alert alert-{{ $class }}">{{ session($key) }}</div>@endif
@endforeach
@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif