{{--
    The place-order gate. When open and the payment block exists it is the one
    primary action; otherwise it is a disabled button carrying a plain reason
    (never an error code). The cart itself arrives with #23, so the enabled
    control links to the menu to start choosing.
--}}
@if ($gate->canOrder)
    <a class="primary-button" href="#menu">Start an order</a>
@else
    <div class="order-gate">
        <button class="primary-button" type="button" disabled aria-disabled="true">Start an order</button>
        <p class="order-gate__reason">{{ $gate->reason }}</p>
    </div>
@endif
