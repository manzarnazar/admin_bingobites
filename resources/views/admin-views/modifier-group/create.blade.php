@extends('layouts.admin.app')

@section('title', translate('Create Modifier Group'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap gap-2 align-items-center mb-4">
            <h2 class="h1 mb-0 d-flex align-items-center gap-2">
                <i class="tio-puzzle-outlined"></i>
                {{ translate('Create_Modifier_Group') }}
            </h2>
        </div>

        <form action="{{ route('admin.modifier-group.store') }}" method="post">
            @csrf
            @include('admin-views.modifier-group.partials._form')

            <div class="d-flex justify-content-end gap-3 mt-4">
                <a href="{{ route('admin.modifier-group.index') }}" class="btn btn-secondary">{{ translate('cancel') }}</a>
                <button type="submit" class="btn btn-primary">{{ translate('submit') }}</button>
            </div>
        </form>
    </div>
@endsection
