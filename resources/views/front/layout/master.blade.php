@include('front.layout.header')
<main id="spa-content" data-spa-root>
    @yield('content')
</main>
@include('front.layout.footer')

        </div>
        @include('front.partials.auth-modal')
        <div class="sidebars">
        	<div class="sidebar right">
	           	<a href="#" class="side_t close_cross" data-action="close" data-side="right"><span></span></a>
	        	<div class="kode_sidebar_right">
	        		<a href="#" class="kode_logo"><img src="{{ asset('images/footer-logo.png') }}" alt=""></a>
	        		<ul class="kode_demos">
	        			<li>
	        				<a href="event-detail.html" data-rel='prettyPhoto'><img src="{{ asset('images/demos/home1.jpg') }}" alt="Default Home Page"><span ><i class="fa fa-search-plus" aria-hidden="true"></i></span></a>
	        			</li>
	        			<li>
	        				<a href="event-organiser.html" data-rel='prettyPhoto'><img src="{{ asset('images/demos/home2.jpg') }}" alt="Home page 2"><span ><i class="fa fa-search-plus" aria-hidden="true"></i></span></a>
	        			</li>
	        			<li>
	        				<a href="shop-items.html" data-rel='prettyPhoto'><img src="{{ asset('images/demos/home3.jpg') }}" alt="Home page 3"><span ><i class="fa fa-search-plus" aria-hidden="true"></i></span></a>
	        			</li>
	        			<li>
	        				<a href="video-list.html" data-rel='prettyPhoto'><img src="{{ asset('images/demos/home4.jpg') }}" alt="Home page 4"><span ><i class="fa fa-search-plus" aria-hidden="true"></i></span></a>
	        			</li>
	        			<li>
	        				<a href="headers.html" data-rel='prettyPhoto'><img src="{{ asset('images/demos/home5.jpg') }}" alt="Home page 5"><span ><i class="fa fa-search-plus" aria-hidden="true"></i></span></a>
	        			</li>
	        			<li>
	        				<a href="blog-detail-leftsidebar.html" data-rel='prettyPhoto'><img src="{{ asset('images/demos/home6.jpg') }}" alt="black version"><span ><i class="fa fa-search-plus" aria-hidden="true"></i></span></a>
	        			</li>
	        		</ul>

	        		<ul class="kf_connect">
                        <li class="facebook"><a href="#"><i class="fa fa-facebook"></i></a></li>
                        <li class="twitter"><a href="#"><i class="fa fa-twitter"></i></a></li>
                        <li class="youtube"><a href="#"><i class="fa fa-youtube"></i></a></li>
                        <li class="dribble"><a href="#"><i class="fa fa-life-ring"></i></a></li>
                        <li class="behance"><a href="#"><i class="fa fa-behance"></i></a></li>
                        <li class="gplus"><a href="#"><i class="fa fa-google-plus"></i></a></li>
                    </ul>
					<p><i aria-hidden="true" class="fa fa-copyright"></i>2018 MUSIC FOREST TEMPLATE Made by KODEFOREST</p>

	        	</div>
	        </div>
	    </div>
	    <div class="search-overlay" id="kode-search-overlay">
		    <button class="close-btn" id="close-btn-button"><i class="fa fa-times"></i></button>
		    <div id="search-wrapper">
		      <form method="get" id="search-from2" action="#">
		        <input type="text" value="" placeholder="Search..." id="search-felid">
		        <i class="fa fa-search search-icon"><input value="" type="submit"></i>
		      </form>
		    </div>
	  	</div>


        <!--Jquery Library-->
        <script src="{{ asset('js/jquery.js') }}"></script>
        <!-- intl-tel-input -->
        <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@22.0.2/build/js/intlTelInput.min.js"></script>
    	<!--Bootstrap core JavaScript-->
        <script src="{{ asset('js/bootstrap.js') }}"></script>
        <!--Slick Slider JavaScript-->
        <script src="{{ asset('js/slick.min.js') }}"></script>
        <!-- Player JavaScript -->
        <script type="text/javascript" src="{{ asset('js/jplayer/jplayer.jukebox.js') }}"></script>
        <script type="text/javascript" src="{{ asset('js/jplayer/jquery.jplayer.min.js') }}"></script>
        <script type="text/javascript" src="{{ asset('js/jplayer/jplayer.playlist.min.js') }}"></script>
        <!--Dl Menu Script-->
        <script src="{{ asset('js/dl-menu/modernizr.custom.js') }}"></script>
        <script src="{{ asset('js/dl-menu/jquery.dlmenu.js') }}"></script>
        <!--chosen JavaScript-->
        <script src="{{ asset('js/chosen.jquery.min.js') }}"></script>
        <!--downcount JavaScript-->
        <script src="{{ asset('js/downcount.js') }}"></script>
        <!--Pretty Photo JavaScript-->
        <script src="{{ asset('js/jquery.prettyPhoto.js') }}"></script>
        <!--masonry JavaScript-->
        <script src="{{ asset('js/masonry.min.js') }}"></script>
        <!--Range slider JavaScript-->
        <script src="{{ asset('js/range-slider.js') }}"></script>
        <!--Search script JavaScript-->
        <script src="{{ asset('js/search-script.js') }}"></script>
        <!--Custom sidebar-->
        <script src="{{ asset('js/sidebar.min.js') }}"></script>
        <!-- bxslider-->
        <script src="{{ asset('js/jquery.bxslider.js') }}"></script>
        <!-- video-->
        <script src="{{ asset('js/video.js') }}"></script>
        <!-- waypoint-->
        <script src="{{ asset('js/waypoint.js') }}"></script>
        <!--Custom JavaScript-->
    	<script src="{{ asset('js/custom.js') }}"></script>
        <script src="{{ asset('js/spa-navigation.js') }}"></script>

        @include('front.partials.auth-scripts')
  </body>

</html>
