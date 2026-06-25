<div class="admin-sections-layout" style="grid-column: 1 / -1;">

    <div class="admin-panel">
        <h3 class="admin-panel-title">Active Discount Coupons</h3>
        <div class="table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Promo Code</th>
                        <th>Discount BDT</th>
                        <th>Coupon Vitals</th>
                        <th>Operations</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($promotions as $promo)
                        <tr>
                            <td style="font-weight: bold; color: var(--accent)">{{ $promo->code }}</td>
                            <td style="color: var(--gold); font-weight: bold;">BDT
                                {{ number_format($promo->discount_amount) }}</td>
                            <td>{{ $promo->description }}</td>
                            <td>
                                <div class="action-btns">
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="setCrudFormMode('promotion-form', {
                                                mode: 'edit',
                                                id: {{ $promo->id }},
                                                action: '{{ route('admin.promotions.update', $promo->id) }}',
                                                title: 'Edit Coupon {{ $promo->code }}',
                                                submitLabel: 'Update Coupon',
                                                fields: {
                                                    code: {{ json_encode($promo->code) }},
                                                    discount_amount: {{ json_encode($promo->discount_amount) }},
                                                    description: {{ json_encode($promo->description) }}
                                                }
                                            })">
                                        Edit
                                    </button>
                                    <form action="{{ route('admin.promotions.destroy', $promo->id) }}" method="POST"
                                        onsubmit="return confirm('Delete this coupon?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 30px; color: var(--text-muted)">No coupons
                                found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="booking-form-sidebar">
        <h3 class="booking-summary-title" id="promotion-form-title">Generate Discount Coupon</h3>
        <form class="booking-form-fields" id="promotion-form" action="{{ route('admin.promotions.store') }}"
            method="POST">
            @csrf
            @method('POST')
            <input type="hidden" name="_edit_id" value="">
            <div class="input-group">
                <label>Coupon Code (e.g. SONYANEW)</label>
                <input type="text" name="code" class="coupon-input" placeholder="Code" required
                    value="{{ old('code') }}">
            </div>
            <div class="input-group">
                <label>Discount Amount BDT</label>
                <input type="number" name="discount_amount" class="coupon-input" placeholder="150" required min="0">
            </div>
            <div class="input-group">
                <label>Coupon Description Detail</label>
                <input type="text" name="description" class="coupon-input" placeholder="Voucher details" required
                    value="{{ old('description') }}">
            </div>
            <button class="btn btn-primary" id="promotion-form-submit" type="submit"
                style="height: 42px; margin-top: 10px;">
                Generate Coupon
            </button>
            <button type="button" class="btn btn-secondary form-cancel-btn" id="promotion-form-cancel"
                onclick="resetCrudForm('promotion-form', '{{ route('admin.promotions.store') }}', 'Generate Discount Coupon', 'Generate Coupon')">
                Cancel Edit
            </button>
        </form>
    </div>

</div>