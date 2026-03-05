@extends('front.layout.master')

@section('content')
    <style>
        .privacy-page { color:#fff; }
        .privacy-page .privacy-main-title { color:#FFC815; }
        .privacy-page .privacy-last-updated { color:#d1ddf2; margin-bottom: 16px; }
        .privacy-page ol { color:#e8edf7; }
        .privacy-page ol > li { margin-bottom:18px; }
        .privacy-page ol > li > strong { color:#FFC815; font-size:28px; }
        .privacy-page ul { margin-top:8px; }
    </style>

    <div class="sub-banner">
        <div class="container">
            <h6>Privacy &amp; Policy</h6>
            <p>Welcome to Tkt House. Your privacy is important to us.</p>
        </div>
    </div>

    <div class="kode_content_wrap privacy-page">
        <section>
            <div class="container">
                <div class="row">
                    <div class="col-md-10 col-md-offset-1">
                        <div class="msl-black title-style-2" style="margin-bottom:25px;">
                            <div class="msl-heading light-color">
                                <h5><span class="privacy-main-title">Privacy &amp; Policy</span></h5>
                            </div>
                        </div>

                        <p class="privacy-last-updated"><strong>Last Updated:</strong> Jan 2026</p>
                        <p style="color:#e8edf7; line-height:1.9; margin-bottom:20px;">
                            Welcome to Tkt House. This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you visit our website and use our services.
                        </p>

                        <ol style="line-height:1.9; padding-left:20px;">
                            <li>
                                <strong>Information We Collect</strong><br>
                                We may collect the following types of information:
                                <ul>
                                    <li><strong>Personal Information</strong>: Full Name, Email Address, Phone Number, Billing Information, and Account Login Details.</li>
                                    <li><strong>Usage Data</strong>: IP Address, Browser Type, Device Information, pages visited on our platform, and date/time of access.</li>
                                    <li><strong>Payment Information</strong>: All transactions are processed through secure third-party payment providers. Tkt House does not store full credit card information on its servers.</li>
                                </ul>
                            </li>

                            <li>
                                <strong>How We Use Your Information</strong><br>
                                We use the collected information for the following purposes:
                                <ul>
                                    <li>To create and manage your account.</li>
                                    <li>To process ticket purchases and payments.</li>
                                    <li>To send booking confirmations and notifications.</li>
                                    <li>To improve our platform and user experience.</li>
                                    <li>To prevent fraud and ensure platform security.</li>
                                    <li>To provide customer support.</li>
                                </ul>
                            </li>

                            <li>
                                <strong>Sharing Your Information</strong><br>
                                We do not sell, trade, or rent your personal information to third parties.
                                <ul>
                                    <li>Payment processing providers.</li>
                                    <li>Event organizers when necessary.</li>
                                    <li>Legal authorities if required by law.</li>
                                </ul>
                            </li>

                            <li>
                                <strong>Data Security</strong><br>
                                We implement industry-standard security measures including:
                                <ul>
                                    <li>Secure HTTPS encryption.</li>
                                    <li>Firewall protection.</li>
                                    <li>Access control to sensitive data.</li>
                                </ul>
                                However, no electronic transmission over the internet can be guaranteed to be 100% secure.
                            </li>

                            <li>
                                <strong>Cookies</strong><br>
                                Tkt House may use cookies and similar tracking technologies to improve website functionality, remember user preferences, and analyze website traffic. You can disable cookies through your browser settings.
                            </li>

                            <li>
                                <strong>User Rights</strong><br>
                                Users have the right to:
                                <ul>
                                    <li>Access their personal data.</li>
                                    <li>Request correction of inaccurate information.</li>
                                    <li>Request deletion of their data (where applicable).</li>
                                </ul>
                                Requests can be sent to our support email.
                            </li>

                            <li>
                                <strong>Third-Party Links</strong><br>
                                Our platform may contain links to third-party websites. We are not responsible for their privacy practices.
                            </li>

                            <li>
                                <strong>Changes to This Policy</strong><br>
                                We may update this Privacy Policy from time to time. Updates will be posted on this page with a revised date.
                            </li>

                            <li>
                                <strong>Contact Us</strong><br>
                                If you have any questions about this Privacy Policy, please contact us: <a href="mailto:support@tkthouse.com">support@tkthouse.com</a>
                            </li>
                        </ol>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
