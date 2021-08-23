@extends('admin.layouts.main')

@section('main_content')
<div class="m-content">
    <div class="m-portlet m-portlet--mobile">
        <div class="m-portlet__head">
            <div class="m-portlet__head-caption">
                <div class="m-portlet__head-title">
                    <h3 class="m-portlet__head-text">
                        Edit Mail Template
                    </h3>
                </div>
            </div>
            <div class="m-portlet__head-tools">
                <a class="btn btn-success btn-sm m-btn--air" id="btnSaveMailTemplate">Save</a>&nbsp;
                <a href="{{url('/email-templates')}}" class="btn btn-info btn-sm m-btn--air">Back</a>
            </div>
        </div>
        <div class="m-portlet__body">
            <form id="frmMailTemplate" class="m-form m-form__section--first m-form--label-align-right">
                <input type="hidden" name="rec_id" value="{{$rec->id}}" />
                <div class="form-group m-form__group row">
                    <label class="col-md-3 col-form-label">Type</label>
                    <div class="col-md-9">
                        <input class="form-control" disabled="disabled" value="{{$rec->type}}">
                    </div>
                </div>
                <div class="form-group m-form__group row">
                    <label class="col-md-3 col-form-label">Active</label>
                    <div class="col-md-9">
                        <label class="m-checkbox">
                            <input type="checkbox" name="is_active" @if($rec->is_active == 1) checked="checked" @endif>
                                   <span></span>
                        </label>
                    </div>
                </div>
                <div class="form-group m-form__group row">
                    <label class="col-md-3 col-form-label">Subject</label>
                    <div class="col-md-9">
                        <input class="form-control" name="subject" value="{{$rec->subject}}">
                    </div>
                </div>
                <div class="form-group m-form__group row">
                    <label class="col-md-3 col-form-label">Content</label>
                    <div class="col-md-9">
                        <textarea rows="10" class="form-control" name="content">{{$rec->content}}</textarea>
                    </div>
                </div>
                <div class="form-group m-form__group row">
                    <label class="col-md-3 col-form-label"></label>
                    <div class="col-md-9">
                        <h5>{{$rec->place_holders}}</h5>
                    </div>
                </div>
                <div class="form-group m-form__group row">
                    <label class="col-md-3 col-form-label">Remarks</label>
                    <div class="col-md-9">
                        <input class="form-control" disabled="disabled" value="{{$rec->remarks}}">
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection