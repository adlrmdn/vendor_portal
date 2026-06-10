@extends('layouts.app')

@section('title', 'Create Work Order')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Create Work Order</h2>
    <a href="{{ route('subcon.admin.orders') }}" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back
    </a>
</div>

<form method="POST" action="{{ route('subcon.admin.orders.store') }}" id="orderForm">
    @csrf
    <div class="row g-4">
        <div class="col-lg-8">
            {{-- Order Details --}}
            <div class="card mb-4">
                <div class="card-header"><h6 class="mb-0">Order Details</h6></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Order Number <span class="text-danger">*</span></label>
                            <input type="text" name="order_number" class="form-control @error('order_number') is-invalid @enderror"
                                value="{{ old('order_number') }}" required>
                            @error('order_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Vendor <span class="text-danger">*</span></label>
                            <select name="vendor_id" class="form-select @error('vendor_id') is-invalid @enderror" required>
                                <option value="">Select vendor...</option>
                                @foreach($vendors as $v)
                                    <option value="{{ $v->id }}" {{ old('vendor_id') == $v->id ? 'selected' : '' }}>{{ $v->name }}</option>
                                @endforeach
                            </select>
                            @error('vendor_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control @error('title') is-invalid @enderror"
                                value="{{ old('title') }}" required>
                            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Order Date <span class="text-danger">*</span></label>
                            <input type="date" name="order_date" class="form-control @error('order_date') is-invalid @enderror"
                                value="{{ old('order_date', date('Y-m-d')) }}" required>
                            @error('order_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Due Date</label>
                            <input type="date" name="due_date" class="form-control" value="{{ old('due_date') }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Order Items --}}
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Order Items</h6>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addItem()">
                        <i class="fas fa-plus me-1"></i> Add Item
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0" id="itemsTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:15%">Item #</th>
                                    <th>Description</th>
                                    <th style="width:12%">Qty</th>
                                    <th style="width:12%">Unit</th>
                                    <th style="width:5%"></th>
                                </tr>
                            </thead>
                            <tbody id="itemsBody">
                                <tr id="item-0">
                                    <td><input type="text" name="items[0][item_number]" class="form-control form-control-sm" required></td>
                                    <td><input type="text" name="items[0][description]" class="form-control form-control-sm" required></td>
                                    <td><input type="number" name="items[0][quantity]" class="form-control form-control-sm" step="0.01" min="0.01" required></td>
                                    <td><input type="text" name="items[0][unit]" class="form-control form-control-sm" value="PCS" required></td>
                                    <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeItem(0)"><i class="fas fa-times"></i></button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-save me-1"></i> Create Order
                    </button>
                    <a href="{{ route('subcon.admin.orders') }}" class="btn btn-outline-secondary w-100 mt-2">Cancel</a>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
let itemCount = 1;
function addItem() {
    const i = itemCount++;
    const row = `<tr id="item-${i}">
        <td><input type="text" name="items[${i}][item_number]" class="form-control form-control-sm" required></td>
        <td><input type="text" name="items[${i}][description]" class="form-control form-control-sm" required></td>
        <td><input type="number" name="items[${i}][quantity]" class="form-control form-control-sm" step="0.01" min="0.01" required></td>
        <td><input type="text" name="items[${i}][unit]" class="form-control form-control-sm" value="PCS" required></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeItem(${i})"><i class="fas fa-times"></i></button></td>
    </tr>`;
    document.getElementById('itemsBody').insertAdjacentHTML('beforeend', row);
}
function removeItem(i) {
    const row = document.getElementById('item-' + i);
    if (document.querySelectorAll('#itemsBody tr').length > 1) row.remove();
}
</script>
@endsection
