<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $product->name }} · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    @include('menu.partials.header')

    <main class="site-main">
        @if ($banner !== null)
            <p class="announcement-banner" role="status">{{ $banner }}</p>
        @endif

        <p class="breadcrumb">
            <a href="{{ route('home') }}">Menu</a>
            @if ($category !== null)
                <span aria-hidden="true">/</span>
                <a href="{{ route('home', ['category' => $category->id]) }}">{{ $category->name }}</a>
            @endif
        </p>

        <article class="product-detail">
            <div class="product-detail__media">
                @if ($product->imageBase64 !== '')
                    <img class="product-detail__image" src="data:image/jpeg;base64,{{ $product->imageBase64 }}" alt="{{ $product->name }}">
                @else
                    <span class="product-detail__placeholder" aria-hidden="true">{{ mb_substr($product->name, 0, 1) }}</span>
                @endif
            </div>

            <div class="product-detail__body">
                <h1 class="product-detail__name">{{ $product->name }}</h1>

                @if ($product->description !== '')
                    <p class="product-detail__description">{{ $product->description }}</p>
                @endif

                <p class="product-detail__price">
                    @if ($product->promo !== null)
                        <s>₱{{ number_format($product->price, 2) }}</s>
                        <span class="product-detail__price-now">₱{{ number_format($product->promo->promoPrice, 2) }}</span>
                        <span class="tag tag--promo">{{ $product->promo->label() }}</span>
                    @else
                        <span class="product-detail__price-now">₱{{ number_format($product->price, 2) }}</span>
                    @endif
                    @if ($isTopThree)
                        <span class="tag tag--top">Top 3</span>
                    @endif
                </p>

                @include('menu.partials.store-badge', ['gate' => $gate])

                @if ($product->addOns !== [])
                    <form class="addon-picker" method="POST" action="{{ route('prefs.add-ons', ['product' => $product->id]) }}">
                        @csrf
                        <fieldset class="addon-picker__set">
                            <legend class="addon-picker__legend">Add-ons</legend>
                            @foreach ($product->addOns as $addOn)
                                <label class="addon-picker__option">
                                    <input type="checkbox" name="addOns[]" value="{{ $addOn->name }}" @checked(in_array($addOn->name, $selectedAddOns, true))>
                                    <span>{{ $addOn->name }}</span>
                                    <span class="addon-picker__price">+₱{{ number_format($addOn->price, 2) }}</span>
                                </label>
                            @endforeach
                        </fieldset>
                        <button type="submit" class="toggle-button">Save my picks</button>
                    </form>
                @endif

                @include('menu.partials.order-gate', ['gate' => $gate])
            </div>

            <form class="product-detail__favorite" method="POST" action="{{ route('favorites.toggle', ['product' => $product->id]) }}">
                @csrf
                <button
                    type="submit"
                    class="favorite-toggle"
                    aria-pressed="{{ $isFavorite ? 'true' : 'false' }}"
                    aria-label="{{ $isFavorite ? 'Remove from favorites' : 'Save to favorites' }}"
                >
                    <span class="favorite-toggle__glyph" aria-hidden="true">{{ $isFavorite ? '♥' : '♡' }}</span>
                    <span class="favorite-toggle__label">{{ $isFavorite ? 'Saved' : 'Save' }}</span>
                </button>
            </form>
        </article>
    </main>
</body>
</html>
