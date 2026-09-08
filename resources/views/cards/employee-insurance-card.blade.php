@php
    /** @var \Illuminate\Support\Collection<int, \App\Support\EmployeeInsuranceCard> $cards */
    $cards = isset($cards) ? $cards : collect([$card]);
    $embedAssets = $embedAssets ?? true;
    $preview = $preview ?? false;
    $printPack = $printPack ?? false;
    $fontSrc = $embedAssets
        ? $cards->first()?->fontDataUri
        : $cards->first()?->fontUrl;
@endphp
<div @class(['employee-insurance-cards', 'employee-insurance-cards--preview' => $preview, 'employee-insurance-cards--print' => $printPack]) dir="ltr" lang="ar">
    <style>
        @font-face {
            font-family: 'Somar Sans';
            src: url('{{ $fontSrc }}') format('truetype');
            font-weight: 600;
            font-style: normal;
        }

        .employee-insurance-cards {
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            gap: 24px;
            padding: 0;
            margin: 0;
            background: #ffffff;
        }

        .employee-insurance-cards *,
        .employee-insurance-cards *::before,
        .employee-insurance-cards *::after {
            box-sizing: border-box;
        }

        .employee-id-card {
            position: relative;
            width: 1004px;
            height: 634px;
            margin: 0;
            padding: 0;
            background: #ffffff;
            overflow: hidden;
            flex: none;
        }

        .employee-id-card__canvas {
            position: absolute;
            inset: 0;
            margin: auto;
        }

        .employee-id-card--front .employee-id-card__canvas {
            width: 972.22px;
            height: 601.8px;
        }

        .employee-id-card--back .employee-id-card__canvas {
            width: 1004px;
            height: 634px;
        }

        .employee-id-card__art {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: fill;
        }

        .employee-id-card__photo {
            position: absolute;
            left: 595.5px;
            top: 167.35px;
            width: 303.59px;
            height: 368.18px;
            object-fit: cover;
            border-radius: 16.12px;
        }

        .employee-id-card__data {
            position: absolute;
            inset: 0;
            overflow: visible;
            pointer-events: none;
        }

        .employee-id-card__data p {
            position: absolute;
            margin: 0;
            padding: 0;
            font-family: 'Somar Sans', 'SomarSans-SemiBold', sans-serif;
            font-weight: 600;
            color: #1a1a24;
            overflow: visible;
            white-space: nowrap;
        }

        .employee-id-card__value {
            left: 36px;
            width: 368px;
            text-align: right;
        }

        .employee-id-card__reference {
            left: 236px;
            top: 58px;
            font-size: 34px;
            line-height: 1.3;
        }

        .employee-id-card__name {
            top: 168px;
            font-size: 24px;
            line-height: 1.55;
            padding-block: 8px;
            direction: rtl;
        }

        .employee-id-card__dob {
            top: 226px;
            font-size: 24px;
            line-height: 1.45;
            padding-block: 8px;
        }

        .employee-id-card__job {
            top: 284px;
            font-size: 24px;
            line-height: 1.45;
            padding-block: 8px;
            direction: rtl;
        }

        .employee-id-card__blood {
            top: 346px;
            font-size: 24px;
            line-height: 1.45;
            padding-block: 8px;
        }

        .employee-id-card__issued {
            left: 603px;
            top: 556px;
            font-size: 24px;
            line-height: 1.35;
        }

        .employee-id-card__barcode {
            position: absolute;
            left: 44px;
            top: 400px;
            width: 532px;
            height: 184px;
        }

        .employee-id-card__barcode svg {
            display: block;
            width: 100%;
            height: 100%;
        }
    </style>

    @foreach ($cards as $card)
        @if ($preview)
            <div class="insurance-card-preview-person">
                <div class="insurance-card-preview-person__head">
                    <h4 class="insurance-card-preview-person__title">{{ $card->heading() }}</h4>
                    <p class="insurance-card-preview-person__meta">{{ $card->name }} · {{ $card->jobTitle }}</p>
                </div>
                <div class="insurance-card-preview-person__faces">
        @endif

        <div @class(['insurance-card-frame' => $preview])>
            <section class="employee-id-card employee-id-card--front">
                <div class="employee-id-card__canvas">
                    <img
                        class="employee-id-card__art"
                        src="{{ $card->frontArtworkUrl }}"
                        alt=""
                        width="972"
                        height="602"
                    >
                    @if ($card->photoSrc($embedAssets))
                        <img
                            class="employee-id-card__photo"
                            src="{{ $card->photoSrc($embedAssets) }}"
                            alt=""
                            width="304"
                            height="368"
                        >
                    @endif
                    <div class="employee-id-card__data">
                        <p class="employee-id-card__reference">{{ $card->reference }}</p>
                        <p class="employee-id-card__value employee-id-card__name">{{ $card->name }}</p>
                        <p class="employee-id-card__value employee-id-card__dob">{{ $card->dateOfBirth }}</p>
                        <p class="employee-id-card__value employee-id-card__job">{{ $card->jobTitle }}</p>
                        <p class="employee-id-card__value employee-id-card__blood">{{ $card->bloodType }}</p>
                        <p class="employee-id-card__issued">{{ $card->issuedAt }}</p>
                    </div>
                    @if ($card->barcodeSvg)
                        <div class="employee-id-card__barcode">
                            {!! $card->barcodeSvg !!}
                        </div>
                    @endif
                </div>
            </section>
        </div>

        <div @class(['insurance-card-frame' => $preview])>
            <section class="employee-id-card employee-id-card--back">
                <div class="employee-id-card__canvas">
                    <img
                        class="employee-id-card__art"
                        src="{{ $card->backArtworkUrl }}"
                        alt=""
                        width="1004"
                        height="634"
                    >
                </div>
            </section>
        </div>

        @if ($preview)
                </div>
            </div>
        @endif
    @endforeach
</div>
