@extends('admin.layouts.main')

@section('main_content')
<div class="m-content">
    <div class="m-portlet m-portlet--mobile">
        <div class="m-portlet__head">
            <div class="m-portlet__head-caption">
                <div class="m-portlet__head-title">
                    <h3 class="m-portlet__head-text">
                        Email Templates
                    </h3>
                </div>
            </div>
        </div>
        <div class="m-portlet__body">
            <table class="table table-striped- table-bordered table-hover table-checkable" id="dt_mail_templates">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Subject</th>
                        <th>Active</th>
                        <th>Remarks</th>
                        <th>Actions</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
@endsection