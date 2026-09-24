<div class="modal-overlay" id="delete-modal" data-deactivate-title="{{ __('ui.confirm_deactivate') }}" data-deactivate-message="{{ __('ui.deactivate_message') }}" data-deactivate-help="{{ __('ui.deactivate_help') }}">
    <div class="modal-card">
        <div class="modal-head">
            <span data-modal-title>Confirm Delete</span>
            <button type="button" class="modal-close js-modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <p data-modal-message>Are you sure you want to delete this record?</p>
            <div class="alert" data-modal-help>This action cannot be undone. Linked historical records are kept for audit purposes.</div>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn outline js-modal-close">Cancel</button>
            <form method="POST" action="#">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn danger">Confirm Delete</button>
            </form>
        </div>
    </div>
</div>
