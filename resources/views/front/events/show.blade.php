       @extends('front.layout.master')

@section('content')
       <!--Sub Banner Wrap Start-->
            <div class="sub-banner">
                <div class="container">
                    <h6>TKTHouse Event Details</h6>
                    <p>Temporary event template: every event link currently shows this design until dashboard integration is completed.</p>
                </div>
            </div>
            <!--Sub Banner Wrap End-->
            <!--Main Content Wrap Start-->
            <section class="kode_content_wrap">
                <div class="container">
					<div class="row">
						<div class="col-md-12">
							<!--KODE-EVENT-CONTER-SECTION START-->
							<div class="kode_event_counter_section">
								<figure>
									<img src="extra-images/event-update1.jpg" alt="">
									<ul class="countdown">
										<li>
											<span class="days">72</span>
											<p class="days_ref">days</p>
										</li>
										<li>
											<span class="hours">13</span>
											<p class="hours_ref">hours</p>
										</li>
										<li>
											<span class="minutes">24</span>
											<p class="minutes_ref">minute</p>
										</li>
										<li>
											<span class="seconds last">00</span>
											<p class="seconds_ref">sec</p>
										</li>
									</ul>
								</figure>

							</div>
							<!--KODE-EVENT-CONTER-SECTION END-->

							<!--KODE_EVENT_CONTER_CAPSTION START-->
							<div class="kode_event_conter_capstion">
								<h2>Techno Pulse Night</h2>
								<div class="counter-meta">
									<ul class="event-media">
										<li><a href="#" data-toggle="tooltip" title="Facebook"><i class="fa fa-facebook"></i></a></li>
										<li><a href="#" data-toggle="tooltip" title="Twitter"><i class="fa fa-twitter"></i></a></li>
										<li><a href="#" data-toggle="tooltip" title="LinkedIn"><i class="fa fa-linkedin"></i></a></li>
										<li><a href="#" data-toggle="tooltip" title="Google Plus"><i class="fa fa-google-plus"></i></a></li>
									</ul>
									<ul class="info-event">
										<li><i class="fa fa-user"></i><a href="#"><span>TKTHouse Team</span></a></li>
										<li><i class="fa fa-comments"></i><a href="#"><span>Booking opens now</span></a></li>
										<li><i class="fa fa-clock-o"></i><a href="#"><span>12 March, 2026</span></a></li>
									</ul>
								</div>
							</div>

							<!--KODE_EVENT_CONTER_CAPSTION END-->
	                        <div class="other-events">
	                            <div class="row ">
	                                <div class="col-md-6 col-sm-6 col-xs-12">
	                                    <!--KODE_EVENT_PLACE_HOLDER START-->
	                                    <div class="kode-event-place-holder">
	                                        <figure>
	                                            <img src="extra-images/event-n1.jpg" alt="">
	                                            <div class="event-frame-over">
	                                                <h2>EVENT DETAILS</h2>
	                                                <ul>
	                                                    <li><h3>Start Date:</h3><span>12-03-26</span></li>
	                                                    <li><h3>END Date:</h3><span>12-03-26</span></li>
	                                                    <li><h3>location:</h3><span>cairo arena</span></li>
	                                                </ul>
	                                            </div>
	                                        </figure>
	                                        <!--KODE_EVENT_PLACE_HOLDER END-->
	                                    </div>
	                                </div>

	                                <div class="col-md-6 col-sm-6 col-xs-12">
	                                    <!--KODE_EVENT_PLACE_HOLDER START-->
	                                    <div class="kode-event-place-holder">
	                                        <figure>
	                                            <img src="extra-images/event-n2.jpg" alt="">
	                                            <div class="event-frame-over">
	                                                <h2>EVENT DETAILS</h2>
	                                                <ul>
	                                                    <li><h3>Start Date:</h3><span>13-03-26</span></li>
	                                                    <li><h3>END Date:</h3><span>13-03-26</span></li>
	                                                    <li><h3>location:</h3><span>cairo arena</span></li>
	                                                </ul>
	                                            </div>
	                                        </figure>
	                                        <!--KODE_EVENT_PLACE_HOLDER END-->
	                                    </div>
	                                </div>

	                                <div class="col-md-12">
	                                    <div class="kode-event-place-holder-capstion">
	                                        <p>Techno Pulse Night delivers an immersive audiovisual experience with top DJs, dynamic stage design, and seamless entry via TKTHouse e-tickets. Current event URL slug: {{ $eventSlug }}. This page is intentionally reused for all event links until live data is connected from the dashboard.</p>
	                                        <figure>
	                                            <div id="map-canvas" class="map-canvas"></div>
	                                        </figure>
	                                        <p>Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>
	                                    </div>
										<div class="ticket-section">
											<div class="msl-black">
		                                        <div class="msl-heading light-color">
		                                            <h5><span>BUY TICKETS TO THIS EVENT</span></h5>
		                                        </div>
	                                        </div>
											<div class="tickets">
												<ul class="kode-tickets-title">
	                                            	<li>Ticket Type</li>
	                                                <li>Price</li>
	                                                <li>Qty</li>
	                                                <li>Cart</li>
												</ul>
												<ul class="ticket-column secnd">
													<li><span>Gold</span></li>
													<li><span>$79.99</span></li>
													<li>
														<input class="input-qty-type" type="number" name="qty" value="1"/>
													</li>
													<li ><a href="#">Get Tickets (Demo)</a></li>
												</ul>
												<ul class="ticket-column">
													<li><span>Sliver</span></li>
													<li><span>$49.99</span></li>
													<li>
														<input class="input-qty-type" type="number" name="qty" value="1"/>
													</li>
													<li><a href="#">Get Tickets (Demo)</a></li>
												</ul>
												<ul class="ticket-column secnd">
													<li><span>Regular</span></li>
													<li><span>$29.99</span></li>
													<li>
														<input class="input-qty-type" type="number" name="qty" value="1"/>
													</li>
													<li><a href="#">Get Tickets (Demo)</a></li>
												</ul>
												<ul class="ticket-column">
													<li><span>Regular</span></li>
													<li><span>$29.99</span></li>
													<li>
														<input class="input-qty-type" type="number" name="qty" value="1"/>
													</li>
													<li><a href="#">Get Tickets (Demo)</a></li>
												</ul>
											</div>
										</div>

										<div class="count-down-timer">
											<ul class="countdown">
												<li>
													<span class="days">72</span>
													<p class="days_ref">days</p>
												</li>
												<li>
													<span class="hours">13</span>
													<p class="hours_ref">hours</p>
												</li>
												<li>
													<span class="minutes">24</span>
													<p class="minutes_ref">minute</p>
												</li>
												<li>
													<span class="seconds last">00</span>
													<p class="seconds_ref">sec</p>
												</li>
											</ul>
											<div class="caption-info">
												<h5>Time until Techno Pulse Night</h5>
												<span>12 March, Cairo Arena (Egypt)</span>
											</div>
										</div>

                                        <div class="kode-slider-speaker">
                                            <div class="msl-black">
		                                        <div class="msl-heading light-color">
		                                            <h5><span>organizer of the events</span></h5>
		                                        </div>
	                                        </div>
                                            <div class="new-album-slider">
                                           		<div class="thumb">
                                                    <figure>
                                                        <img src="extra-images/speakers-1.jpg" alt="">
                                                    </figure>
                                                    <div class="speaker-event-content">
                                                        <h3>Sophia</h3>
                                                        <span>organizer</span>
                                                        <strong class="bottom-border"></strong>
                                                        <ul class="kf_connect">
							                                <li class="facebook"><a data-toggle="tooltip" href="#"><i class="fa fa-facebook"></i></a></li>
							                                <li class="twitter"><a data-toggle="tooltip" href="#"><i class="fa fa-twitter"></i></a></li>
							                                <li class="gplus"><a data-toggle="tooltip" href="#"><i class="fa fa-google-plus"></i></a></li>
							                            </ul>
                                                    </div>
                                                </div>
                                                <div class="thumb">
                                                    <figure>
                                                        <img src="extra-images/speakers-2.jpg" alt="">
                                                    </figure>
                                                    <div class="speaker-event-content">
                                                        <h3>Jacob</h3>
                                                        <span>organizer</span>
                                                        <strong class="bottom-border"></strong>
                                                        <ul class="kf_connect">
							                                <li class="facebook"><a data-toggle="tooltip" href="#"><i class="fa fa-facebook"></i></a></li>
							                                <li class="twitter"><a data-toggle="tooltip" href="#"><i class="fa fa-twitter"></i></a></li>
							                                <li class="gplus"><a data-toggle="tooltip" href="#"><i class="fa fa-google-plus"></i></a></li>
							                            </ul>
                                                    </div>
                                                </div>
                                                <div class="thumb">
                                                    <figure>
                                                        <img src="extra-images/speakers-3.jpg" alt="">
                                                    </figure>
                                                    <div class="speaker-event-content">
                                                        <h3>Emma</h3>
                                                        <span>organizer</span>
                                                        <strong class="bottom-border"></strong>
                                                        <ul class="kf_connect">
							                                <li class="facebook"><a data-toggle="tooltip" href="#"><i class="fa fa-facebook"></i></a></li>
							                                <li class="twitter"><a data-toggle="tooltip" href="#"><i class="fa fa-twitter"></i></a></li>
							                                <li class="gplus"><a data-toggle="tooltip" href="#"><i class="fa fa-google-plus"></i></a></li>
							                            </ul>
                                                    </div>
                                                </div>
                                            </div>
	                                    </div>
	                                    <!-- Kode Comment Section Start -->
			                            <div class="kode-comments">
			                                <!--Heading Start-->
			                                <div class="msl-black">
			                                    <div class="msl-heading light-color">
			                                        <h5><span>There are 03 comment For this Article</span></h5>
			                                	</div>
			                                </div>
			                                <!--Heading End-->
			                                <ul id="kode-comment" class="comment">
			                                    <li>
			                                        <div class="comment_item">
			                                            <!-- Kode Comment Form Start -->
			                                            <div class="kode-author">
			                                                <figure>
			                                                    <img src="extra-images/comment1.jpg" alt="KODEFOREST">
			                                                </figure>
			                                                <div class="kode-author-content">
			                                                    <div class="kode-author-head">
			                                                        <h5><a href="#">Brain Deo</a></h5>
			                                                        <span>10-05-2016</span>
			                                                    </div>
			                                                    <p>Nullam ac urna eu felis dapibus condimentum sit amet a augue. Sed non neque elit. Sed ut imperdiet nisi. Proin condimentum fermentum nunc. Etiam pharetra, erat sed fermentum feugiat, velittiam pharetra, erat sed fermentum feugiat, velit</p>
			                                                    <a class="comment-reply-link" href="#">Reply</a>
			                                                </div>
			                                            </div>
			                                            <!-- Kode Comment Form End -->
			                                        </div>
			                                        <ul class="children">
			                                            <li>
			                                                <div class="comment_item">
			                                                    <!-- Kode Comment Form Start -->
			                                                    <div class="kode-author">
			                                                        <figure>
			                                                            <img src="extra-images/comment2.jpg" alt="KODEFOREST">
			                                                        </figure>
			                                                        <div class="kode-author-content">
			                                                            <div class="kode-author-head">
			                                                                <h5><a href="#">Brain Deo</a></h5>
			                                                                <span>10-05-2016</span>
			                                                            </div>
			                                                            <p>Nullam ac urna eu felis dapibus condimentum sit amet a augue. Sed non neque elit. Sed ut imperdiet nisi. Proin condimentum fermentum nunc. Etiam pharetra, erat sed fermentum feugiat, velittiam pharetra, erat sed fermentum feugiat, velit</p>
			                                                            <a class="comment-reply-link" href="#">Reply</a>
			                                                        </div>
			                                                    </div>
			                                                    <!-- Kode Comment Form End -->
			                                                </div>
			                                            </li>
			                                        </ul>
			                                    </li>
			                                    <li>
			                                        <div class="comment_item">
			                                            <!-- Kode Comment Form Start -->
			                                            <div class="kode-author">
			                                                <figure>
			                                                    <img src="extra-images/comment3.jpg" alt="KODEFOREST">
			                                                </figure>
			                                                <div class="kode-author-content">
			                                                    <div class="kode-author-head">
			                                                        <h5><a href="#">Brain Deo</a></h5>
			                                                        <span>10-05-2016</span>
			                                                    </div>
			                                                    <p>Nullam ac urna eu felis dapibus condimentum sit amet a augue. Sed non neque elit. Sed ut imperdiet nisi. Proin condimentum fermentum nunc. Etiam pharetra, erat sed fermentum feugiat, velittiam pharetra, erat sed fermentum feugiat, velit</p>
			                                                    <a class="comment-reply-link" href="#">Reply</a>
			                                                </div>
			                                            </div>
			                                            <!-- Kode Comment Form End -->
			                                        </div>
			                                    </li>
			                                </ul>
			                                <div class="div-border"></div>
			                            </div>
			                            <!-- Kode Comment Section End -->
			                            <!-- Kode Comment Form Start -->
			                            <div class="kode-comment-form">
			                                <!--Heading Start-->
			                                <div class="msl-black">
			                                    <div class="msl-heading light-color">
			                                        <h5><span>Add Your Comments</span></h5>
			                                	</div>
			                                </div>
			                                <!--Heading End-->
			                                <p></p>
			                                <form method="post" id="commentform" class="comment-form light_bg">
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
			                            <!-- Kode Comment Form End -->
	                                </div>
	                            </div>
	                        </div>
						</div>
	

            		</div>
        		</div>
            </section>
            <!--Main Content Wrap End-->

@endsection
