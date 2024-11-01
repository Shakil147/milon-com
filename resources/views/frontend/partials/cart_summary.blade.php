<div class="card border-0 shadow-sm rounded">
    <div class="card-header">
        <h3 class="fs-16 fw-600 mb-0">{{translate('Summary')}}</h3>
        <div class="text-right">
            <span class="badge badge-inline badge-primary">
                {{ count($carts) }} 
                {{translate('Items')}}
            </span>
        </div>
    </div>

    <div class="card-body">
        @if (addon_is_activated('club_point'))
            @php
                $total_point = 0;
            @endphp
            @foreach ($carts as $key => $cartItem)
                @php
                    $product = \App\Models\Product::find($cartItem['product_id']);
                    $total_point += $product->earn_point * $cartItem['quantity'];
                @endphp
            @endforeach
            
            <div class="rounded px-2 mb-2 bg-soft-primary border-soft-primary border">
                {{ translate("Total Club point") }}:
                <span class="fw-700 float-right">{{ $total_point }}</span>
            </div>
        @endif

        <table class="table">
            <thead>
                <tr>
                    <th class="product-name">{{translate('Product')}}</th>
                    <th class="product-total text-right">{{translate('Total')}}</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $subtotal = 0;
                    $tax = 0;
                    $shipping = 0;
                    $product_shipping_cost = 0;
                @endphp
                @foreach ($carts as $key => $cartItem)
                    @php
                        $product = \App\Models\Product::find($cartItem['product_id']);
                        $subtotal += $cartItem['price'] * $cartItem['quantity'];
                        $tax += $cartItem['tax'] * $cartItem['quantity'];
                        $product_shipping_cost = $cartItem['shipping_cost'];
                        $shipping += $product_shipping_cost;
                        
                        $product_name_with_choice = $product->getTranslation('name');
                        if ($cartItem['variant'] != null) {
                            $product_name_with_choice = $product->getTranslation('name').' - '.$cartItem['variant'];
                        }
                    @endphp
                    <tr class="cart_item">
                        <td class="product-name">
                            {{ $product_name_with_choice }}
                            <strong class="product-quantity">
                                × {{ $cartItem['quantity'] }}
                            </strong>
                        </td>
                        <td class="product-total text-right">
                            <span class="pl-4 pr-0">{{ single_price($cartItem['price'] * $cartItem['quantity']) }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="table">
            <tfoot>
                <!-- Subtotal -->
                <tr class="cart-subtotal">
                    <th>{{translate('Subtotal')}}</th>
                    <td class="text-right">
                        <span class="fw-600">{{ single_price($subtotal) }}</span>
                    </td>
                </tr>

                <!-- Tax -->
                <tr class="cart-shipping">
                    <th>{{translate('Tax')}}</th>
                    <td class="text-right">
                        <span class="font-italic">{{ single_price($tax) }}</span>
                    </td>
                </tr>

                <!-- Delivery Charge -->
                <tr class="cart-shipping">
                    <th>{{translate('Delivery Charge')}}</th>
                    <td class="text-right">
                        <span class="font-italic">{{ single_price($shipping) }}</span>
                    </td>
                </tr>

                <!-- Coupon Discount (if applicable) -->
                @if ($carts->sum('discount') > 0)
                <tr class="cart-shipping">
                    <th>{{translate('Coupon Discount')}}</th>
                    <td class="text-right">
                        <span class="font-italic">- {{ single_price($carts->sum('discount')) }}</span>
                    </td>
                </tr>
                @endif

                <!-- Total (Final Calculation in PHP) -->
                @php
                    // Calculate total price
                    $total = $subtotal + $tax + $shipping;

                    // Apply club points (if available)
                    if (Session::has('club_point')) {
                        $total -= Session::get('club_point');
                    }

                    // Apply coupon discount (if available)
                    if ($carts->sum('discount') > 0) {
                        $total -= $carts->sum('discount');
                    }

                    // Set advance payment (fixed amount)
                    $advance_payment = 200;

                    // Calculate total due after advance payment
                    $total_due = $total - $advance_payment;
                @endphp

                <!-- Total after applying discounts and points -->
                <tr class="cart-total">
                    <th><span class="strong-600">{{translate('Total')}}</span></th>
                    <td class="text-right">
                        <strong><span>{{ single_price($total) }}</span></strong>
                    </td>
                </tr>

                <!-- Advance Payment -->
                <tr class="advance-payment-row">
                    <th>{{translate('এ্যাডভান্স টাকা পেমেন্ট করে অর্ডার কনফার্ম করুন')}}</th>
                    <td class="text-right">
                        <span class="font-italic">{{ single_price($advance_payment) }}</span>
                    </td>
                </tr>

                <!-- Total Due on Delivery -->
                <tr class="total-due-row">
                    <th>{{translate(' পণ্য নেওয়ার সময় ডেলিভারিম্যান কে পরিশোধ করবেন')}}</th>
                    <td class="text-right">
                        <span class="font-italic">{{ single_price($total_due) }}</span>
                    </td>
                </tr>

                <!-- Redeem Point (if applicable) -->
                @if (Session::has('club_point'))
                <tr class="cart-shipping">
                    <th>{{translate('Redeem point')}}</th>
                    <td class="text-right">
                        <span class="font-italic">{{ single_price(Session::get('club_point')) }}</span>
                    </td>
                </tr>
                @endif
            </tfoot>
        </table>

        <!-- Coupon System and Club Points Application Forms -->
        @if (addon_is_activated('club_point'))
            @if (Session::has('club_point'))
                <div class="mt-3">
                    <form action="{{ route('checkout.remove_club_point') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="input-group">
                            <div class="form-control">{{ Session::get('club_point')}}</div>
                            <div class="input-group-append">
                                <button type="submit" class="btn btn-primary">{{translate('Remove Redeem Point')}}</button>
                            </div>
                        </div>
                    </form>
                </div>
            @endif
        @endif

        @if (Auth::check() && get_setting('coupon_system') == 1)
            @if ($carts[0]['discount'] > 0)
                <div class="mt-3">
                    <form id="remove-coupon-form" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="owner_id" value="{{ $carts[0]['owner_id'] }}">
                        <div class="input-group">
                            <div class="form-control">{{ $carts[0]['coupon_code'] }}</div>
                            <div class="input-group-append">
                                <button type="button" id="coupon-remove" class="btn btn-primary">{{translate('Change Coupon')}}</button>
                            </div>
                        </div>
                    </form>
                </div>
            @else
                <div class="mt-3">
                    <form id="apply-coupon-form" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="owner_id" value="{{ $carts[0]['owner_id'] }}">
                        <div class="input-group">
                            <input type="text" class="form-control" name="code" onkeydown="return event.key != 'Enter';" placeholder="{{translate('Have coupon code? Enter here')}}" required>
                            <div class="input-group-append">
                                <button type="button" id="coupon-apply" class="btn btn-primary">{{translate('Apply')}}</button>
                            </div>
                        </div>
                    </form>
                </div>
            @endif
        @endif
    </div>
</div>
