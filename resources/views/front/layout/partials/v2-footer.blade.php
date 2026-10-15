<footer class="tkt-footer">
    <div class="tkt-container">
        <div class="tkt-footer__grid">
            <div>
                <a class="tkt-footer__logo" href="{{ route('front.home') }}"><img src="{{ $logoFooter }}" alt="{{ $siteName }}"></a>
                <p class="tkt-footer__tagline">TKT House curates premium techno events and makes booking your tickets simple and secure.</p>
                @if(file_exists(public_path('images/payments-methods.png')))
                    <img src="{{ asset('images/payments-methods.png') }}" alt="Accepted payment methods" class="tkt-footer__payments" loading="lazy">
                @endif
            </div>

            <div>
                <h4 class="tkt-footer__title">Explore</h4>
                <ul class="tkt-footer__links">
                    <li><a href="{{ route('front.home') }}">Home</a></li>
                    <li><a href="{{ route('front.about') }}">About</a></li>
                    <li><a href="{{ route('front.events') }}">Events</a></li>
                    <li><a href="{{ route('front.contact') }}">Contact</a></li>
                </ul>
            </div>

            <div>
                <h4 class="tkt-footer__title">Legal</h4>
                <ul class="tkt-footer__links">
                    <li><a href="{{ route('front.terms') }}">Terms and Conditions</a></li>
                    <li><a href="{{ route('front.privacy') }}">Privacy &amp; Policy</a></li>
                    <li><a href="{{ route('front.cookie') }}">Cookie Policy</a></li>
                </ul>
            </div>

            <div>
                <h4 class="tkt-footer__title">Contact us</h4>
                <a href="mailto:support@tkthouse.com">support@tkthouse.com</a>
                <div class="tkt-footer__social">
                    <a href="https://www.instagram.com/tkthouse.eg?igsh=MTd0MnJjcWJ4bG9yZA==" target="_blank" rel="noopener" aria-label="Instagram"><i class="fa fa-instagram" aria-hidden="true"></i></a>
                </div>
            </div>
        </div>
    </div>
    <div class="tkt-footer__bottom">
        <div class="tkt-container">
            <span>© {{ date('Y') }} {{ $siteName }}. All rights reserved.</span>
            <span>Development by <a href="https://waf-agency.com/" target="_blank" rel="noopener">WAF Agency</a></span>
        </div>
    </div>
</footer>
