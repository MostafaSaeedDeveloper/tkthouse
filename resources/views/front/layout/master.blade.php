@include('front.layout.header')
<main id="spa-content" data-spa-root>
    @yield('content')
</main>
@include('front.layout.footer')

        </div>
        <!-- Auth Modal — Premium Dark Gold -->
        <style>
        @import url('https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500&display=swap');

        /* ── Override Bootstrap modal backdrop ── */
        #login-register1.modal { z-index: 99999; }
        #login-register1 .modal-dialog {
            max-width: 860px;
            width: 95vw;
            margin: 5vh auto;
        }
        #login-register1 .modal-content {
            background: #0a0a0e;
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 16px;
            box-shadow: 0 32px 80px rgba(0,0,0,0.7), 0 0 0 1px rgba(245,184,0,0.06);
            overflow: hidden;
            padding: 0;
            position: relative;
        }

        /* ── Close button ── */
        #login-register1 .auth-close {
            position: absolute;
            top: 16px; right: 16px;
            width: 32px; height: 32px;
            border-radius: 50%;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.08);
            color: #6b6b7e;
            font-size: 16px;
            line-height: 1;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: background 0.2s, color 0.2s;
            z-index: 10;
        }
        #login-register1 .auth-close:hover { background: rgba(232,68,90,0.15); color: #e8445a; border-color: rgba(232,68,90,0.3); }

        /* ── Tab switcher ── */
        .auth-tabs {
            display: flex;
            border-bottom: 1px solid rgba(255,255,255,0.07);
            background: #060608;
        }
        .auth-tab {
            flex: 1;
            padding: 18px 24px;
            font-family: 'Syne', sans-serif;
            font-size: 12px; font-weight: 700;
            letter-spacing: 2px; text-transform: uppercase;
            color: #6b6b7e;
            cursor: pointer;
            border: none; background: transparent;
            border-bottom: 2px solid transparent;
            margin-bottom: -1px;
            transition: color 0.2s, border-color 0.2s;
        }
        .auth-tab.active { color: #f5b800; border-bottom-color: #f5b800; }
        .auth-tab:hover:not(.active) { color: #e8e8ef; }

        /* ── Panels ── */
        .auth-panel { display: none; padding: 36px 40px 40px; }
        .auth-panel.active { display: block; animation: authFade 0.25s ease; }
        @keyframes authFade { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:translateY(0); } }

        /* ── Two-column layout (login only) ── */
        .auth-two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 48px;
        }
        .auth-divider {
            position: relative;
            display: flex; align-items: center; justify-content: center;
        }
        .auth-divider::before {
            content: '';
            position: absolute;
            top: 0; bottom: 0; left: 50%;
            width: 1px;
            background: rgba(255,255,255,0.07);
        }
        .auth-divider-label {
            position: relative; z-index: 1;
            background: #0a0a0e;
            padding: 8px 12px;
            font-family: 'Syne', sans-serif;
            font-size: 11px; font-weight: 700;
            letter-spacing: 2px; text-transform: uppercase;
            color: #6b6b7e;
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 99px;
        }
        @media (max-width: 640px) {
            .auth-two-col { grid-template-columns: 1fr; gap: 32px; }
            .auth-divider { display: none; }
            .auth-panel { padding: 28px 22px 32px; }
        }

        /* ── Section heading ── */
        .auth-heading { margin-bottom: 24px; }
        .auth-heading h3 {
            font-family: 'Syne', sans-serif;
            font-size: 20px; font-weight: 800;
            color: #fff; margin: 0 0 6px;
            letter-spacing: -0.3px;
        }
        .auth-heading p {
            font-family: 'DM Sans', sans-serif;
            font-size: 13px; color: #6b6b7e;
            margin: 0;
        }

        /* ── Form fields ── */
        .auth-field { margin-bottom: 14px; }
        .auth-field label {
            display: block;
            font-family: 'DM Sans', sans-serif;
            font-size: 10px; font-weight: 500;
            letter-spacing: 1px; text-transform: uppercase;
            color: #6b6b7e; margin-bottom: 6px;
        }
        .auth-field input {
            width: 100%;
            background: #16161d;
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 8px;
            color: #e8e8ef;
            font-family: 'DM Sans', sans-serif;
            font-size: 14px;
            padding: 11px 14px;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
            box-sizing: border-box;
        }
        .auth-field input::placeholder { color: #3a3a4a; }
        .auth-field input:focus {
            border-color: rgba(245,184,0,0.5);
            box-shadow: 0 0 0 3px rgba(245,184,0,0.08);
        }

        /* ── Submit button ── */
        .auth-submit {
            width: 100%;
            background: #f5b800;
            color: #000;
            font-family: 'Syne', sans-serif;
            font-size: 13px; font-weight: 800;
            letter-spacing: 1px; text-transform: uppercase;
            border: none; border-radius: 8px;
            padding: 13px 24px;
            cursor: pointer;
            margin-top: 6px;
            transition: background 0.2s, transform 0.1s;
        }
        .auth-submit:hover { background: #ffc820; }
        .auth-submit:active { transform: scale(0.99); }

        /* ── Gold accent line at top ── */
        .auth-modal-accent {
            height: 3px;
            background: linear-gradient(90deg, transparent 0%, #f5b800 40%, #c99300 70%, transparent 100%);
        }
        </style>

        <div id="login-register1" class="modal fade" role="dialog">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="auth-modal-accent"></div>
                    <button type="button" class="auth-close" data-dismiss="modal" aria-label="Close">✕</button>

                    {{-- Tabs --}}
                    <div class="auth-tabs">
                        <button class="auth-tab active" data-auth-tab="login">Sign In</button>
                        <button class="auth-tab" data-auth-tab="register">Create Account</button>
                    </div>

                    {{-- Sign In Panel --}}
                    <div class="auth-panel active" id="auth-panel-login">
                        <div class="auth-heading">
                            <h3>Welcome back</h3>
                            <p>Sign in to your account to continue</p>
                        </div>
                        <form method="POST" action="{{ route('front.customer.login.store') }}">
                            @csrf
                            <input type="hidden" name="redirect_to" value="{{ request('redirect') }}" data-redirect-target>
                            <div class="auth-field">
                                <label>Email or Username</label>
                                <input type="text" name="login" placeholder="you@example.com" required>
                            </div>
                            <div class="auth-field">
                                <label>Password</label>
                                <input type="password" name="password" placeholder="••••••••" required>
                            </div>
                            <button class="auth-submit" type="submit">Sign In →</button>
                        </form>
                        <p style="text-align:center;margin-top:18px;font-family:'DM Sans',sans-serif;font-size:13px;color:#6b6b7e;">
                            Don't have an account?
                            <a href="#" data-auth-switch="register" style="color:#f5b800;text-decoration:none;font-weight:500;">Create one</a>
                        </p>
                    </div>

                    {{-- Register Panel --}}
                    <div class="auth-panel" id="auth-panel-register">
                        <div class="auth-heading">
                            <h3>Create your account</h3>
                            <p>Join us and start booking tickets</p>
                        </div>
                        <form method="POST" action="{{ route('front.customer.register.store') }}">
                            @csrf
                            <input type="hidden" name="redirect_to" value="{{ request('redirect') }}" data-redirect-target>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                                <div class="auth-field" style="margin-bottom:0">
                                    <label>Full Name</label>
                                    <input type="text" name="name" placeholder="John Doe" required>
                                </div>
                                <div class="auth-field" style="margin-bottom:0">
                                    <label>Email Address</label>
                                    <input type="email" name="email" placeholder="you@example.com" required>
                                </div>
                            </div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:14px;">
                                <div class="auth-field" style="margin-bottom:0">
                                    <label>Password</label>
                                    <input type="password" name="password" placeholder="••••••••" required>
                                </div>
                                <div class="auth-field" style="margin-bottom:0">
                                    <label>Confirm Password</label>
                                    <input type="password" name="password_confirmation" placeholder="••••••••" required>
                                </div>
                            </div>
                            <button class="auth-submit" type="submit" style="margin-top:20px;">Create Account →</button>
                        </form>
                        <p style="text-align:center;margin-top:18px;font-family:'DM Sans',sans-serif;font-size:13px;color:#6b6b7e;">
                            Already have an account?
                            <a href="#" data-auth-switch="login" style="color:#f5b800;text-decoration:none;font-weight:500;">Sign in</a>
                        </p>
                    </div>

                </div>
            </div>
        </div>

        <script>
        (function(){
            // Tab switching
            document.querySelectorAll('[data-auth-tab]').forEach(function(tab){
                tab.addEventListener('click', function(){
                    var target = tab.dataset.authTab;
                    document.querySelectorAll('.auth-tab').forEach(function(t){ t.classList.remove('active'); });
                    document.querySelectorAll('.auth-panel').forEach(function(p){ p.classList.remove('active'); });
                    tab.classList.add('active');
                    document.getElementById('auth-panel-' + target).classList.add('active');
                });
            });
            // Inline "switch" links
            document.querySelectorAll('[data-auth-switch]').forEach(function(link){
                link.addEventListener('click', function(e){
                    e.preventDefault();
                    var target = link.dataset.authSwitch;
                    document.querySelectorAll('[data-auth-tab]').forEach(function(t){
                        t.classList.toggle('active', t.dataset.authTab === target);
                    });
                    document.querySelectorAll('.auth-panel').forEach(function(p){ p.classList.remove('active'); });
                    document.getElementById('auth-panel-' + target).classList.add('active');
                });
            });
        })();
        </script>
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

        <script>
            (function () {
                var isAuthenticated = document.body && document.body.dataset.authenticated === '1';
                if (isAuthenticated) {
                    return;
                }

                document.addEventListener('click', function (event) {
                    var link = event.target.closest('a.tkt-checkout-btn');
                    if (!link) {
                        return;
                    }

                    event.preventDefault();
                    event.stopPropagation();
                    if (typeof event.stopImmediatePropagation === 'function') {
                        event.stopImmediatePropagation();
                    }

                    var checkoutUrl = link.getAttribute('href') || '';
                    document.querySelectorAll('[data-redirect-target]').forEach(function (input) {
                        input.value = checkoutUrl;
                    });

                    if (window.jQuery && window.jQuery.fn && window.jQuery.fn.modal) {
                        window.jQuery('#login-register1').modal('show');
                    }
                }, true);
            })();
        </script>

  </body>

</html>
