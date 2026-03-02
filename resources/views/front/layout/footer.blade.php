
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
                .msl-footer{background:linear-gradient(90deg,#111927 0%,#1a2435 100%);padding:50px 0 30px;}
                .msl-footer-layout{display:grid;grid-template-columns:minmax(200px,1.2fr) repeat(3,minmax(160px,1fr));gap:30px;align-items:flex-start;}
                .msl-footer-brand img{max-height:70px;margin-bottom:18px;}
                .msl-footer-brand p{color:#d6deec;line-height:1.6;margin:0;font-size:15px;}
                .msl-footer-title{font-size:34px;color:#fff;font-family:'Poppins',sans-serif;margin:0 0 16px;}
                .msl-footer-links{list-style:none;padding:0;margin:0;}
                .msl-footer-links li{margin-bottom:12px;}
                .msl-footer-links a,.msl-footer-contact{color:#d6deec;font-size:18px;line-height:1.6;}
                .msl-footer-links a:hover{color:#fff;}
                .msl-footer-social{display:flex;gap:10px;margin:0 0 14px;list-style:none;padding:0;}
                .msl-footer-social a{height:42px;width:42px;border-radius:50%;display:flex;align-items:center;justify-content:center;border:1px solid rgba(255,255,255,.25);color:#fff;font-size:20px;}
                .msl-footer-social a:hover{background:#fff;color:#111927;}
                .msl-footer-payment-placeholder{display:block;border:1px dashed rgba(255,255,255,.35);border-radius:10px;padding:14px;color:#d6deec;font-size:14px;margin-bottom:12px;}
                .msl-footer-pci{max-width:130px;height:auto;display:block;}
                .msl-copyright{background:#111927;border-top:1px solid rgba(255,255,255,.1);}
                .msl-copyright p{color:#fff!important;margin:0;}
                @media (max-width:991px){.msl-footer-layout{grid-template-columns:repeat(2,minmax(160px,1fr));}.msl-footer-title{font-size:30px;}}
                @media (max-width:575px){.msl-footer{padding:40px 0 24px;}.msl-footer-layout{grid-template-columns:1fr;gap:24px;}.msl-footer-title{font-size:28px;}}
            </style>
            <footer class="msl-footer">
                <div class="container msl-footer-layout">
                    <div class="msl-footer-brand">
                        <a href="{{ route('front.home') }}"><img src="{{ \App\Support\SystemSettings::get('site_logo_footer') ? asset('storage/'.\App\Support\SystemSettings::get('site_logo_footer')) : asset('images/footer-logo.png') }}" alt="{{ \App\Support\SystemSettings::get('site_name', 'TKT House') }}"></a>
                        <p>Your access. Your moment.</p>
                    </div>

                    <div>
                        <h4 class="msl-footer-title">Links</h4>
                        <ul class="msl-footer-links">
                            <li><a href="#">Terms and Condition</a></li>
                        </ul>
                    </div>

                    <div>
                        <h4 class="msl-footer-title">Follow Us</h4>
                        <ul class="msl-footer-social">
                            <li>
                                <a href="https://www.instagram.com/tkthouse.eg?igsh=MTd0MnJjcWJ4bG9yZA==" target="_blank" rel="noopener" aria-label="Instagram">
                                    <i class="fa fa-instagram"></i>
                                </a>
                            </li>
                        </ul>
                        <a href="#" class="msl-footer-payment-placeholder">Payment methods image placeholder</a>
                        <img src="https://fawaterk.com/_next/static/media/pci.6bdbbd92.svg" alt="PCI DSS Compliant" class="msl-footer-pci">
                    </div>

                    <div>
                        <h4 class="msl-footer-title">Contact Us</h4>
                        <a class="msl-footer-contact" href="mailto:support@tkthouse.com">support@tkthouse.com</a>
                    </div>
                </div>
            </footer>

            <div class="msl-copyright theme-bg">
                <div class="container">
                    <p class="text-center" style="color:#000">© {{ date('Y') }} {{ \App\Support\SystemSettings::get('site_name', 'TKT House') }}. All rights reserved.</p>
                </div>
            </div>
