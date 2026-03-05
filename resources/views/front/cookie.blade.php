@extends('front.layout.master')

@section('content')
    <style>
        .cookie-page { color:#fff; }
        .cookie-page .cookie-main-title { color:#FFC815; }
        .cookie-page .cookie-last-updated { color:#d1ddf2; margin-bottom: 16px; }
        .cookie-page ol { color:#e8edf7; }
        .cookie-page ol > li { margin-bottom:18px; }
        .cookie-page ol > li > strong { color:#FFC815; font-size:28px; }
        .cookie-page ul { margin-top:8px; }
    </style>

    <div class="sub-banner">
        <div class="container">
            <h6>Cookie Policy</h6>
            <p>This Cookie Policy explains how Ticket House uses cookies and similar technologies.</p>
        </div>
    </div>

    <div class="kode_content_wrap cookie-page">
        <section>
            <div class="container">
                <div class="row">
                    <div class="col-md-10 col-md-offset-1">
                        <div class="msl-black title-style-2" style="margin-bottom:25px;">
                            <div class="msl-heading light-color">
                                <h5><span class="cookie-main-title">Cookie Policy</span></h5>
                            </div>
                        </div>

                        <p class="cookie-last-updated"><strong>Last Updated:</strong> Jan 2026</p>
                        <p style="color:#e8edf7; line-height:1.9; margin-bottom:20px;">
                            This Cookie Policy explains how Ticket House uses cookies and similar technologies when you visit our website.
                            By using our website, you consent to the use of cookies in accordance with this policy.
                        </p>

                        <ol style="line-height:1.9; padding-left:20px;">
                            <li>
                                <strong>What Are Cookies</strong><br>
                                Cookies are small text files that are stored on your device when you visit a website. They help websites function properly, improve user experience, and provide information to the website owners.
                                <br><br>
                                Cookies may be stored temporarily during your session or permanently on your device.
                            </li>

                            <li>
                                <strong>How We Use Cookies</strong><br>
                                Ticket House uses cookies for several purposes, including:
                                <ul>
                                    <li>Ensuring the website functions properly.</li>
                                    <li>Remembering your login session.</li>
                                    <li>Storing your preferences and settings.</li>
                                    <li>Improving the performance and usability of the platform.</li>
                                    <li>Analyzing traffic and user behavior on the website.</li>
                                    <li>Preventing fraud and enhancing security.</li>
                                </ul>
                            </li>

                            <li>
                                <strong>Types of Cookies We Use</strong><br>
                                <ul>
                                    <li><strong>Essential Cookies:</strong> Necessary for the website to operate correctly. Without these cookies, services such as ticket purchasing and account login may not function properly.</li>
                                    <li><strong>Performance and Analytics Cookies:</strong> Help us understand how visitors interact with our website by collecting information anonymously so we can improve the platform.</li>
                                    <li><strong>Functionality Cookies:</strong> Allow the website to remember choices you make, such as language preferences or login details.</li>
                                    <li><strong>Security Cookies:</strong> Help us detect suspicious activity and protect user accounts and transactions.</li>
                                </ul>
                            </li>

                            <li>
                                <strong>Third-Party Cookies</strong><br>
                                Some cookies may be placed by trusted third-party services used by Ticket House, such as:
                                <ul>
                                    <li>Payment processing providers.</li>
                                    <li>Analytics services.</li>
                                    <li>Security and fraud prevention tools.</li>
                                </ul>
                                These third parties have their own privacy and cookie policies.
                            </li>

                            <li>
                                <strong>Managing Cookies</strong><br>
                                You can manage or disable cookies through your browser settings.
                                <br><br>
                                Please note that disabling certain cookies may affect the functionality of the website and limit your ability to use some features.
                            </li>

                            <li>
                                <strong>Updates to This Cookie Policy</strong><br>
                                Ticket House may update this Cookie Policy from time to time. Any changes will be posted on this page with the updated revision date.
                            </li>

                            <li>
                                <strong>Contact Us</strong><br>
                                If you have any questions about our Cookie Policy, please contact us: <a href="mailto:support@tkthouse.com">support@tkthouse.com</a>
                            </li>
                        </ol>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
