{{-- No <form>: in Simplify settings it sits inside Simplify's own form, whose Save calls save() (data-simplify-save). --}}
<div data-simplify-unsaved data-simplify-save="save">
    {{ $this->form }}
</div>
