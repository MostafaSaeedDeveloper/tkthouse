
            <!--Flicker Slider Wrap Start-->
            <div class="msl-flicker">
                <!--Flicker Slider Wrap Start-->
                <div class="flicker-slider">
                    <!--Flicker Slider Thumb Start-->
                    <div class="thumb">
                        <img src="{{asset('extra-images/flicker1.jpg')}}" alt="TKTHouse">
                    </div>
                    <!--Flicker Slider Thumb End-->
                    <!--Flicker Slider Thumb Start-->
                    <div class="thumb">
                        <img src="{{asset('extra-images/flicker2.jpg')}}" alt="TKTHouse">
                    </div>
                    <!--Flicker Slider Thumb End-->
                    <!--Flicker Slider Thumb Start-->
                    <div class="thumb">
                        <img src="{{asset('extra-images/flicker3.jpg')}}" alt="TKTHouse">
                    </div>
                    <!--Flicker Slider Thumb End-->
                    <!--Flicker Slider Thumb Start-->
                    <div class="thumb">
                        <img src="{{asset('extra-images/flicker4.jpg')}}" alt="TKTHouse">
                    </div>
                    <!--Flicker Slider Thumb End-->
                    <!--Flicker Slider Thumb Start-->
                    <div class="thumb">
                        <img src="{{asset('extra-images/flicker5.jpg')}}" alt="TKTHouse">
                    </div>
                    <!--Flicker Slider Thumb End-->
                    <!--Flicker Slider Thumb Start-->
                    <div class="thumb">
                        <img src="{{asset('extra-images/flicker3.jpg')}}" alt="TKTHouse">
                    </div>
                    <!--Flicker Slider Thumb End-->
                </div>
                <!--Flicker Slider End-->
            </div>
            <!--Flicker Slider Wrap End-->

            <style>
                .msl-footer {
                    background: linear-gradient(120deg, #0c1524 0%, #15233b 58%, #1c2b44 100%);
                    position: relative;
                    overflow: hidden;
                    padding: 48px 0 30px;
                    border-top: 1px solid rgba(255,255,255,.08);
                }
                .msl-footer-layout {
                    display: grid;
                    grid-template-columns: 1.4fr 1fr 1fr 1fr;
                    gap: 34px;
                    align-items: start;
                }
                .msl-footer::after {
                    content: "";
                    position: absolute;
                    width: 320px;
                    height: 320px;
                    right: -120px;
                    bottom: -150px;
                    border-radius: 50%;
                    background: radial-gradient(circle, rgba(255,200,21,.45) 0%, rgba(255,200,21,.18) 38%, rgba(255,200,21,0) 75%);
                    pointer-events: none;
                }
                .msl-footer-layout { position: relative; z-index: 1; }
                .msl-footer-col-brand { grid-column: 1; }
                .msl-footer-col-links { grid-column: 2; }
                .msl-footer-col-social { grid-column: 3; text-align: center; }
                .msl-footer-col-contact { grid-column: 4; }

                .msl-footer-brand > a > img { max-height: 70px; margin-bottom: 16px; }
                .msl-footer-payment-methods {
                    width: 100%;
                    max-width: 280px;
                    height: auto;
                    display: block;
                    margin-bottom: 12px;
                    border-radius: 8px;
                }
                .msl-footer-pci { max-width: 130px; height: auto; display: block; }

                .msl-footer-title {
                    font-size: 20px;
                    color: #FFC815;
                    font-family: 'Poppins', sans-serif;
                    margin: 0 0 14px;
                    line-height: 1.2;
                    font-weight: 700;
                    letter-spacing: .2px;
                }

                .msl-footer-links { list-style: none; padding: 0; margin: 0; }
                .msl-footer-links li { margin-bottom: 10px; }
                .msl-footer-links a,
                .msl-footer-contact {
                    color: #d1ddf2;
                    font-size: 18px;
                    line-height: 1.6;
                }
                .msl-footer-links a:hover,
                .msl-footer-contact:hover { color: #ffd44d; }

                .msl-footer-social { display: flex; gap: 10px; margin: 0 0 14px; list-style: none; padding: 0; justify-content: center; }
                .msl-footer-social a {
                    height: 42px;
                    width: 42px;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    border: 1px solid rgba(255,255,255,.22);
                    background: rgba(255,255,255,.04);
                    color: #fff;
                    font-size: 20px;
                }
                .msl-footer-social a:hover { background: #ffd44d; color: #132238; border-color: #ffd44d; }

                .msl-copyright {
                    background: #0b1320;
                    border-top: 1px solid rgba(255,255,255,.08);
                }
                .msl-copyright p { color: #cfd8ea !important; margin: 0; font-size: 15px; }

                @media (max-width: 1199px) {
                    .msl-footer-layout { grid-template-columns: repeat(2, minmax(220px, 1fr)); }
                    .msl-footer-col-brand,
                    .msl-footer-col-links,
                    .msl-footer-col-social,
                    .msl-footer-col-contact { grid-column: auto; }
                }
                @media (max-width: 575px) {
                    .msl-footer { padding: 38px 0 22px; }
                    .msl-footer-layout { grid-template-columns: 1fr; gap: 22px; }
                    .msl-footer-title { font-size: 22px; }
                }
            </style>

            <footer class="msl-footer">
                <div class="container msl-footer-layout">
                    <div class="msl-footer-brand msl-footer-col-brand">
                        <a href="{{ route('front.home') }}"><img src="{{ \App\Support\SystemSettings::get('site_logo_footer') ? asset('storage/'.\App\Support\SystemSettings::get('site_logo_footer')) : asset('images/footer-logo.png') }}" alt="{{ \App\Support\SystemSettings::get('site_name', 'TKT House') }}"></a>
                        <img src="{{ asset('images/payments-methods.png') }}" alt="Payment Methods" class="msl-footer-payment-methods">
                    </div>

                    <div class="msl-footer-col-links">
                        <h4 class="msl-footer-title">Links</h4>
                        <ul class="msl-footer-links">
                            <li><a href="{{ route('front.terms') }}">Terms and Conditions</a></li>
                        </ul>
                        <img src="{{ asset('images/pci.svg') }}" alt="PCI DSS Compliant" class="msl-footer-pci">
                    </div>

                    <div class="msl-footer-col-social">
                        <h4 class="msl-footer-title">Follow Us</h4>
                        <ul class="msl-footer-social">
                            <li>
                                <a href="https://www.instagram.com/tkthouse.eg?igsh=MTd0MnJjcWJ4bG9yZA==" target="_blank" rel="noopener" aria-label="Instagram">
                                    <i class="fa fa-instagram"></i>
                                </a>
                            </li>
                        </ul>
                    </div>

                    <div class="msl-footer-col-contact">
                        <h4 class="msl-footer-title">Contact Us</h4>
                        <a class="msl-footer-contact" href="mailto:support@tkthouse.com">support@tkthouse.com</a>
                    </div>
                </div>
            </footer>

            <div class="msl-copyright">
                <div class="container">
                    <p class="text-center">© {{ date('Y') }} {{ \App\Support\SystemSettings::get('site_name', 'TKT House') }}. All rights reserved.</p>
                </div>
            </div>
