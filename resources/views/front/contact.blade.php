@extends('front.layout.master')

@section('content')
<div class="sub-banner">
    <div class="container">
        <h6>Contact</h6>
        <p>For support, partnerships, and event organization inquiries, we’re ready to help.</p>
    </div>
</div>

<div class="kode_content_wrap">
    <section>
        <div class="container">
            <div class="row">
                <div class="col-md-6 col-sm-6">
                    <div class="widget-contact">
                        <h4 class="widget-title2">Send Us a Message</h4>
                        <form method="post" class="light_bg comment-form">
                            <div class="kode-left-comment-sec">
                                <div class="kf_commet_field">
                                    <input placeholder="Full Name" type="text">
                                </div>
                                <div class="kf_commet_field">
                                    <input placeholder="Phone Number" type="text">
                                </div>
                                <div class="kf_commet_field">
                                    <input placeholder="Email Address" type="email">
                                </div>
                            </div>
                            <div class="kode-textarea">
                                <textarea placeholder="Write your message"></textarea>
                            </div>
                            <p class="form-submit"><input type="submit" class="submit btn-1 theme-bg" value="Send"></p>
                        </form>
                    </div>
                </div>
                <div class="col-md-6 col-sm-6">
                    <div class="widget-contact social-contact">
                        <h4 class="widget-title2">Contact Details</h4>
                        <p>TKTHouse for techno event management and online ticket booking.</p>
                        <ul class="kf_contact_meta">
                            <li><span class="fa fa-phone"></span><p>+20 100 000 0000</p></li>
                            <li><span class="fa fa-envelope"></span><p>hello@tkthouse.com</p></li>
                            <li><span class="fa fa-map-marker"></span><p>Cairo, Egypt</p></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
