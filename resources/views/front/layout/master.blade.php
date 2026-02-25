@include('front.layout.header')
@yield('content')
@include('front.layout.footer')

    <div id="login-register1" class="modal fade" role="dialog">
        <div class="modal-dialog">
            <div class="modal-content">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <div class="ms-login-form">
                    <form>
                        <div class="ms_width_off50">
                            <div class="ms-heading2">
                                <h3>Sign in</h3>
                                <p>Welcome back! Sign in to your account</p>
                            </div>
                            <div class="input-felid">
                                <label>Username or email address</label>
                                <input type="text" placeholder="username or email address*">
                            </div>
                            <div class="input-felid">
                                <label>Password</label>
                                <input type="password" placeholder="password*">
                            </div>
                            <div class="pull-left">
                                <input type="checkbox" name="remember" id="remember-me">
                                <span class="box"></span>
                                <label for="remember-me" class="ck-title">Remember me</label>
                            </div>
                            <div class="pull-right">
                                <a href="#" class="fpw">Forgot password?</a>
                            </div>
                            <div class="btn-submit">
                                <button class="btn-normal2" type="submit">Login</button>
                            </div>
                        </div>
                        <sup>Or</sup>
                        <div class="ms_width_off50 bg-trans">
                            <div class="ms-heading2">
                                <h3>Create new account</h3>
                                <p>Create your TKTHouse account</p>
                            </div>
                            <div class="input-felid">
                                <label>Email address</label>
                                <input type="text" placeholder="email address*">
                            </div>
                            <div class="btn-submit margin-15">
                                <button class="btn-normal2" type="submit">Register</button>
                            </div>
                            <h4 class="title-point">Sign up to:</h4>
                            <ul class="check-points">
                                <li><p>Book event tickets faster</p></li>
                                <li><p>Track your bookings easily</p></li>
                                <li><p>Get updates about new events</p></li>
                            </ul>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    </div>

    <script src="{{ asset('js/jquery.js') }}"></script>
    <script src="{{ asset('js/bootstrap.js') }}"></script>
    <script src="{{ asset('js/slick.min.js') }}"></script>
    <script type="text/javascript" src="{{ asset('js/jplayer/jplayer.jukebox.js') }}"></script>
    <script type="text/javascript" src="{{ asset('js/jplayer/jquery.jplayer.min.js') }}"></script>
    <script type="text/javascript" src="{{ asset('js/jplayer/jplayer.playlist.min.js') }}"></script>
    <script src="{{ asset('js/dl-menu/modernizr.custom.js') }}"></script>
    <script src="{{ asset('js/dl-menu/jquery.dlmenu.js') }}"></script>
    <script src="{{ asset('js/chosen.jquery.min.js') }}"></script>
    <script src="{{ asset('js/downcount.js') }}"></script>
    <script src="{{ asset('js/jquery.prettyPhoto.js') }}"></script>
    <script src="{{ asset('js/masonry.min.js') }}"></script>
    <script src="{{ asset('js/range-slider.js') }}"></script>
    <script src="{{ asset('js/search-script.js') }}"></script>
    <script src="{{ asset('js/sidebar.min.js') }}"></script>
    <script src="{{ asset('js/jquery.bxslider.js') }}"></script>
    <script src="{{ asset('js/video.js') }}"></script>
    <script src="{{ asset('js/waypoint.js') }}"></script>
    <script src="{{ asset('js/custom.js') }}"></script>

  </body>
</html>
