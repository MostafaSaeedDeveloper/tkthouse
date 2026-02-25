@include('front.layout.header')
<main id="spa-content" data-spa-root>
    @yield('content')
</main>
@include('front.layout.footer')

        </div>
		<!-- Modal -->
		<div id="login-register1" class="modal fade" role="dialog">
		  	<div class="modal-dialog">
			    <!-- Modal content-->
			    <div class="modal-content">
			    	<button type="button" class="close" data-dismiss="modal">&times;</button>
			      	<div class="ms-login-form">
  						<form>
  							<div class="ms_width_off50">
      							<div class="ms-heading2">
      								<h3>Sign in</h3>
      								<p>welcome back! sign in to your account</p>
      							</div>
      							<div class="input-felid">
      								<label>username or email address</label>
      								<input type="text" placeholder="user name or email address*">
      							</div>
      							<div class="input-felid">
      								<label>password</label>
      								<input type="text" placeholder="password*">
      							</div>
      							<div class="pull-left">
      								<input type="checkbox" name="remember" id="clik">
      								<span class="box"></span>
      								<label for="clik" class="ck-title">remember me</label>
      							</div>
      							<div class="pull-right">
      								<a href="#" class="fpw">forgotten password?</a>
      							</div>
      							<div class="btn-submit">
      								<button class="btn-normal2" type="submit">login</button>
      							</div>
  							</div>
  							<sup>Or</sup>
  							<div class="ms_width_off50 bg-trans">
      							<div class="ms-heading2">
      								<h3>create new account</h3>
      								<p>create your very own tkthouse account</p>
      							</div>
      							<div class="input-felid">
      								<label>email address</label>
      								<input type="text" placeholder="user name or email address*">
      							</div>
      							<div class="btn-submit margin-15">
      								<button class="btn-normal2" type="submit">register</button>
      							</div>
      							<h4 class="title-point">sign up today and you will be able to:</h4>
      							<ul class="check-points">
      								<li><p>speed your way through the checkout</p></li>
      								<li><p>track your orders easily</p></li>
      								<li><p>keep a record of all your purchases</p></li>
      							</ul>
  							</div>
  						</form>
  					</div>
			    </div>
		  	</div>
		</div>
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

  </body>

</html>
