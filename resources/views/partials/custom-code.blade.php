@if(filled($model->custom_css))
    <style>{!! $model->custom_css !!}</style>
@endif
@if(filled($model->custom_html))
    <div class="custom-html mt-8">{!! $model->custom_html !!}</div>
@endif
@if(filled($model->custom_js))
    <script>{!! $model->custom_js !!}</script>
@endif
