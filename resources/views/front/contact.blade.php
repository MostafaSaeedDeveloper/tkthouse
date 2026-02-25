@extends('front.layout.master')

@section('content')

          <div class="kode_content_wrap">
                <section>
                    <div class="container">
                        <div class="map-wrap">
                            <div id="map-canvas"></div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 col-sm-6">
                                <div class="widget-contact">
                                    <h4 class="widget-title2">Get In Touch</h4>
                                    <form method="post" id="commentform" class="light_bg comment-form">
                                        <div class="kode-left-comment-sec">
                                            <div class="kf_commet_field">
                                                <input placeholder="Full Name*" name="author" type="text" value="" data-default="Name*" size="30">
                                            </div>
                                            <div class="kf_commet_field">
                                                <input placeholder="Phone Number*" name="email" type="text" value="" data-default="Email*" size="30">
                                            </div>
                                            <div class="kf_commet_field">
                                                <input placeholder="Email Address*" name="email" type="text" value="" data-default="Email*" size="30">
                                            </div>
                                            <div class="kf_commet_field full-width-kode">
                                                <input placeholder="Website" name="url" type="text" value="" data-default="Website" size="30">
                                            </div>
                                        </div>
                                        <div class="kode-textarea">
                                            <textarea placeholder="Type Your Comments*" name="comment"></textarea>
                                        </div>
                                        <p class="form-submit "><input name="submit" type="submit" class="submit btn-1 theme-bg" value="Send Now"></p>
                                    </form>
                                </div>
                            </div>
                            <div class="col-md-6 col-sm-6">
                                <div class="widget-contact social-contact">
                                    <h4 class="widget-title2">Contact Us</h4>
                                    <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nulla sagittis lacinia tellus. Nullam venenatis a sem non dictum. Aliquam orci ipsum, malesuada lacinia faucibus nec, bibendum a enim...</p>
                                    <ul class="kf_contact_meta">
                                        <li>
                                            <span class="fa fa-phone"></span>
                                            <p>(+92) - 334 - 2797084</p>
                                        </li>
                                        <li>
                                            <span class="fa fa-envelope"></span>
                                            <p>example@kodeblog.com</p>
                                        </li>
                                        <li>
                                            <span class="fa fa-map-marker"></span>
                                            <p>90, Downtown St, USA</p>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
            <!--Main Content Wrap End-->

@endsection
