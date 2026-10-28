@if($errors->any())
<div role="alert" class="mb-5 rounded-xl bg-red-50 p-4 text-sm text-red-800"><ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
