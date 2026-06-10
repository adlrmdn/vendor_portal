@extends('layouts.app')

@section('title', 'Admin Settings')

@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">System Settings</h1>
        </div>

        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-cogs me-2"></i>Configuration</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.settings.update') }}" method="POST">
                    @csrf
                    
                    @if($settings->isEmpty())
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>No settings found. You can add them via database or I will initialize defaults if needed.
                        </div>
                    @else
                        @foreach($settings->groupBy('group') as $group => $groupSettings)
                            <h6 class="text-uppercase text-muted border-bottom pb-2 mb-3 mt-4">{{ ucfirst($group) }} Settings</h6>
                            @foreach($groupSettings as $setting)
                                <div class="mb-3 row">
                                    <label for="setting_{{ $setting->key }}" class="col-sm-3 col-form-label fw-bold">
                                        {{ str_replace('_', ' ', ucfirst($setting->key)) }}
                                    </label>
                                    <div class="col-sm-6">
                                        @if($setting->type == 'textarea')
                                            <textarea class="form-control" name="settings[{{ $setting->key }}]" id="setting_{{ $setting->key }}" rows="3">{{ $setting->value }}</textarea>
                                        @elseif($setting->type == 'boolean')
                                            <div class="form-check form-switch mt-2">
                                                <input class="form-check-input" type="checkbox" name="settings[{{ $setting->key }}]" value="1" id="setting_{{ $setting->key }}" {{ $setting->value == '1' ? 'checked' : '' }}>
                                            </div>
                                        @else
                                            <input type="text" class="form-control" name="settings[{{ $setting->key }}]" id="setting_{{ $setting->key }}" value="{{ $setting->value }}">
                                        @endif
                                        @if($setting->description)
                                            <div class="form-text">{{ $setting->description }}</div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        @endforeach
                    @endif

                    <div class="mt-5 pt-3 border-top">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
