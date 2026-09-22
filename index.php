<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, user-scalable=no">
    <meta name="format-detection" content="telephone=no, email=no, address=no">
    <title>Kazakhstan History Quiz & Essay</title>
    <script>
        (function () {
            const themes = ['light', 'dark', 'ocean', 'sunset', 'forest', 'royal', 'mono', 'kahoot'];
            let theme = 'kahoot';
            try {
                const saved = localStorage.getItem('designTheme');
                if (themes.includes(saved)) theme = saved;
            } catch (e) {}
            if (theme !== 'light') document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <link rel="stylesheet" href="style.css?v=61">
    <link rel="stylesheet" href="games.css?v=3">
    <link rel="stylesheet" href="mafia.css?v=1">
</head>
<body>

    <!-- Loading screen: stays until the pictures are ready -->
    <div class="site-loader" id="site-loader" role="status" aria-live="polite">
        <div class="loader-inner">
            <div class="loader-ring"></div>
            <div class="loader-title">Kazakhstan History</div>
            <div class="loader-text">Loading<span class="loader-dots"><i>.</i><i>.</i><i>.</i></span></div>
            <div class="loader-bar"><span id="loader-bar-fill"></span></div>
        </div>
    </div>

    <!-- Decorative watermark: not a link, not clickable -->
    <div class="maten-watermark" aria-hidden="true">
        <img src="maten-logo.png" alt="" width="26" height="26" draggable="false">
        <div class="maten-watermark-text">
            <span class="maten-watermark-by">by Maten services</span>
            <span class="maten-watermark-url">https://maten.pro</span>
        </div>
    </div>

    <audio id="bg-music" src="Music/General.mp3" loop preload="auto"></audio>

    <div class="answer-toast hidden" id="answer-toast">
        <span class="answer-toast-icon icon-svg" id="answer-toast-icon"></span>
        <span id="answer-toast-text">Correct</span>
    </div>

    <div class="top-bar">
        <button class="icon-btn" id="menu-btn" onclick="toggleMenu()" aria-label="Menu">
            <span class="hamburger-icon"><span></span><span></span><span></span></span>
        </button>
        <button class="icon-btn" id="mute-btn" onclick="toggleMute()" aria-label="Sound">
            <span id="mute-icon" class="icon-svg"></span>
        </button>
    </div>

    <div class="site-course-brand" aria-hidden="true">
        <img src="assets/itu-logo.png" alt="" draggable="false">
        <span>IT3-MKM2601</span>
    </div>

    <!-- Account bar: only shown on the start screen -->
    <div class="account-bar hidden" id="account-bar">
        <button class="btn-join" id="join-btn" onclick="openAuthScreen()">Join</button>
        <div class="account-info hidden" id="account-info">
            <span id="account-email-display"></span>
            <button class="account-logout-btn" id="logout-btn" onclick="logoutUser()" aria-label="Log out">
                <span class="icon-svg" id="logout-icon"></span>
            </button>
        </div>
    </div>

    <!-- Auth overlay -->
    <div class="auth-overlay hidden" id="auth-overlay">
        <div class="auth-card">
            <button class="auth-close" onclick="closeAuthScreen()" aria-label="Close">
                <span class="icon-svg" id="auth-close-icon"></span>
            </button>

            <div id="auth-choice-screen">
                <h2 data-i18n="auth_welcome">Welcome</h2>
                <button class="btn-auth-choice" onclick="showAuthForm('signin')" data-i18n="auth_signin">Sign In</button>
                <button class="btn-auth-choice" onclick="showAuthForm('signup')" data-i18n="auth_signup">Sign Up</button>
            </div>

            <div id="auth-signin-screen" class="hidden">
                <h2 data-i18n="auth_signin">Sign In</h2>
                <input type="email" id="signin-email" class="auth-input" placeholder="Email" data-i18n-placeholder="auth_email_placeholder" autocomplete="email">
                <input type="password" id="signin-password" class="auth-input" placeholder="Password" data-i18n-placeholder="auth_password_placeholder" autocomplete="current-password" onkeydown="if(event.key==='Enter') submitSignIn()">
                <p class="auth-error" id="signin-error"></p>
                <button class="btn-auth-choice" onclick="submitSignIn()" data-i18n="auth_signin">Sign In</button>
                <button class="auth-back" onclick="showAuthChoice()" data-i18n="auth_back">Back</button>
            </div>

            <div id="auth-signup-screen" class="hidden">
                <h2 data-i18n="auth_signup">Sign Up</h2>
                <input type="email" id="signup-email" class="auth-input" placeholder="Email" data-i18n-placeholder="auth_email_placeholder" autocomplete="email">
                <input type="password" id="signup-password" class="auth-input" placeholder="Password" data-i18n-placeholder="auth_password_placeholder" autocomplete="new-password">
                <input type="password" id="signup-password2" class="auth-input" placeholder="Repeat password" data-i18n-placeholder="auth_repeat_password_placeholder" autocomplete="new-password" onkeydown="if(event.key==='Enter') submitSignUp()">
                <p class="auth-error" id="signup-error"></p>
                <button class="btn-auth-choice" onclick="submitSignUp()" data-i18n="auth_signup">Sign Up</button>
                <button class="auth-back" onclick="showAuthChoice()" data-i18n="auth_back">Back</button>
            </div>
        </div>
    </div>

    <!-- Promo code -->
    <div class="confirm-overlay hidden" id="promo-overlay">
        <div class="confirm-card">
            <h3 data-i18n="promo_title">Promo Code</h3>
            <input type="text" id="promo-input" class="auth-input promo-input" placeholder="Enter promo code" data-i18n-placeholder="promo_placeholder" onkeydown="if(event.key==='Enter') submitPromoCode()">
            <p class="auth-error" id="promo-error"></p>
            <div class="confirm-actions">
                <button class="btn-confirm-cancel" onclick="closePromoModal()" data-i18n="cancel">Cancel</button>
                <button class="btn-confirm-yes" onclick="submitPromoCode()" data-i18n="promo_redeem">Redeem</button>
            </div>
        </div>
    </div>

    <!-- Admin Panel (full page, admin-only) -->
    <div class="admin-fullpage hidden" id="admin-panel-page">
        <div class="admin-fullpage-inner">
            <button class="btn-back-page" onclick="closeAdminPanel()">
                <span class="icon-svg" id="admin-back-icon"></span> <span data-i18n="admin_back">Back</span>
            </button>
            <h2 data-i18n="admin_panel_title">Admin Panel</h2>
            <div class="admin-actions">
                <button class="btn-admin-action" onclick="openAdminSettings()" data-i18n="admin_settings_button">Admin Settings</button>
                <button class="btn-admin-action" onclick="openUsersPage()" data-i18n="admin_users_button">Users</button>
            </div>
        </div>
    </div>

    <!-- Admin Settings modal -->
    <div class="confirm-overlay hidden" id="admin-settings-overlay">
        <div class="confirm-card admin-settings-card">
            <button class="auth-close" onclick="closeAdminSettings()" aria-label="Close">
                <span class="icon-svg" id="admin-settings-close-icon"></span>
            </button>
            <h3 data-i18n="admin_settings_title">Admin Settings</h3>
            <div class="admin-settings-row">
                <span data-i18n="admin_language_label">Site Language</span>
                <div class="lang-switch">
                    <button class="lang-btn" id="lang-btn-en" onclick="setSiteLanguage('en')">EN</button>
                    <button class="lang-btn" id="lang-btn-kk" onclick="setSiteLanguage('kk')">KK</button>
                    <button class="lang-btn" id="lang-btn-ru" onclick="setSiteLanguage('ru')">RU</button>
                </div>
            </div>
            <button class="btn-admin-action active-toggle" id="toggle-test-guard-btn" onclick="toggleTestGuard()" data-i18n="admin_test_guard_button">Test Guard: ON</button>
            <button class="btn-admin-action" id="toggle-admin-copy-btn" onclick="toggleAdminCopy()" data-i18n="admin_copy_button">Admin Copy</button>
            <button class="btn-admin-action" id="toggle-show-answers-btn" onclick="toggleShowAllAnswers()" data-i18n="admin_show_answers_button">Show All Correct Answers</button>
        </div>
    </div>

    <!-- Users page (full page, admin-only) -->
    <div class="admin-fullpage hidden" id="users-page">
        <div class="admin-fullpage-inner">
            <button class="btn-back-page" onclick="closeUsersPage()">
                <span class="icon-svg" id="users-back-icon"></span> <span data-i18n="admin_back">Back</span>
            </button>
            <h2 data-i18n="admin_users_title">Users</h2>
            <div class="users-counts">
                <div class="users-count-box"><span id="registered-count">0</span><small data-i18n="users_registered_label">Registered</small></div>
                <div class="users-count-box"><span id="admin-count">0</span><small data-i18n="users_admin_label">Admins</small></div>
                <div class="users-count-box"><span id="guest-count">0</span><small data-i18n="users_guest_label">Guest</small></div>
            </div>
            <div class="users-tabs">
                <button class="users-tab active" id="tab-users-btn" onclick="switchUsersTab('users')" data-i18n="users_tab_users">Users</button>
                <button class="users-tab" id="tab-admins-btn" onclick="switchUsersTab('admins')" data-i18n="users_tab_admins">Admins</button>
                <button class="users-tab" id="tab-guests-btn" onclick="switchUsersTab('guest')" data-i18n="users_tab_guest">Guest</button>
            </div>
            <input type="text" id="users-search-input" class="auth-input" placeholder="Search by nickname or email" data-i18n-placeholder="users_search_placeholder" oninput="filterUsersList()">
            <div class="users-list" id="users-list"></div>
        </div>
    </div>

    <div class="menu-panel hidden" id="menu-panel">
        <ul>
            <li><button class="menu-item" onclick="goHome()"><span class="icon-svg" id="home-icon"></span> <span data-i18n="menu_home">Home</span></button></li>
            <li><button class="menu-item" onclick="openMusicModal()"><span class="icon-svg" id="music-icon"></span> <span data-i18n="menu_music_label">Music</span></button></li>
            <li><button class="menu-item" onclick="renameFromMenu()"><span class="icon-svg" id="rename-icon"></span> <span data-i18n="menu_rename">Rename</span></button></li>
            <li><button class="menu-item" onclick="openPromoModal()"><span class="icon-svg" id="promo-icon"></span> <span data-i18n="menu_promo">Promo Code</span></button></li>
            <li><button class="menu-item" id="history-menu-btn" onclick="openHistoryModal()"><span class="icon-svg" id="history-icon"></span> <span data-i18n="history_title">Test History</span></button></li>
            <li><button class="menu-item hidden" id="admin-panel-menu-btn" onclick="openAdminPanel()"><span class="icon-svg" id="admin-panel-icon"></span> <span data-i18n="menu_admin_panel">Admin Panel</span></button></li>
            <li><button class="menu-item" onclick="openDesignModal()"><span class="icon-svg" id="design-icon"></span> <span data-i18n="menu_theme_label">Design</span></button></li>
            <li class="menu-devs" aria-hidden="true">
                <div class="menu-devs-title">Разработчики сайта</div>
                <div class="menu-devs-ticker">
                    <ul class="menu-devs-names">
                        <li>Erkebulan</li>
                        <li>Damir</li>
                        <li>Elnar</li>
                        <li>Ilias</li>
                        <li>Sultan</li>
                        <li>Sanzhar</li>
                        <li>Erkebulan</li>
                    </ul>
                </div>
            </li>
        </ul>
    </div>

    <!-- Week picker: vertical drum used by Weekly Test and Essay modes -->
    <div class="confirm-overlay hidden" id="week-picker-overlay" onclick="if(event.target===this) closeWeekPicker()">
        <div class="confirm-card week-picker-card">
            <button class="modal-close" onclick="closeWeekPicker()" aria-label="Close"><span id="week-picker-close-icon" class="icon-svg"></span></button>
            <h3 id="week-picker-title">Weekly Test</h3>
            <div class="wheel" id="week-wheel">
                <div class="wheel-band"></div>
                <div class="wheel-scroll" id="week-wheel-scroll" tabindex="0" role="listbox" aria-label="Choose a week"></div>
            </div>
            <p class="wheel-caption" id="week-wheel-caption"></p>
            <button class="btn" onclick="confirmWeekPicker()" data-i18n="mode_start">START</button>
        </div>
    </div>

    <div class="confirm-overlay hidden" id="music-overlay" onclick="if(event.target===this) closeMusicModal()">
        <div class="confirm-card music-card">
            <button class="modal-close" onclick="closeMusicModal()" aria-label="Close"><span id="music-close-icon" class="icon-svg"></span></button>
            <h3 data-i18n="menu_music_label">Music</h3>
            <div class="music-options">
                <button class="music-option sound-track-btn" id="sound-track-general" onclick="setSoundTrack('general')" data-i18n="menu_music_general">General</button>
                <button class="music-option sound-track-btn" id="sound-track-standard" onclick="setSoundTrack('standard')" data-i18n="menu_music_standard">Standard</button>
            </div>
        </div>
    </div>

    <div class="confirm-overlay hidden" id="design-overlay" onclick="if(event.target===this) closeDesignModal()">
        <div class="confirm-card design-card">
            <button class="modal-close" onclick="closeDesignModal()" aria-label="Close"><span id="design-close-icon" class="icon-svg"></span></button>
            <h3 data-i18n="design_modal_title">Choose Design</h3>
            <p class="confirm-subtext" data-i18n="design_modal_subtitle">Pick a look for the site.</p>
            <div class="design-grid">
                <button class="design-option" id="theme-swatch-light" onclick="setDesignTheme('light')">
                    <span class="design-preview preview-light"></span>
                    <span class="design-info"><strong>Light</strong><span>Clean white and orange</span></span>
                </button>
                <button class="design-option" id="theme-swatch-dark" onclick="setDesignTheme('dark')">
                    <span class="design-preview preview-dark"></span>
                    <span class="design-info"><strong>Dark</strong><span>Black, fire and contrast</span></span>
                </button>
                <button class="design-option" id="theme-swatch-ocean" onclick="setDesignTheme('ocean')">
                    <span class="design-preview preview-ocean"></span>
                    <span class="design-info"><strong>Ocean</strong><span>Navy, teal and blue</span></span>
                </button>
                <button class="design-option" id="theme-swatch-sunset" onclick="setDesignTheme('sunset')">
                    <span class="design-preview preview-sunset"></span>
                    <span class="design-info"><strong>Sunset</strong><span>Pink, purple and warm light</span></span>
                </button>
                <button class="design-option" id="theme-swatch-forest" onclick="setDesignTheme('forest')">
                    <span class="design-preview preview-forest"></span>
                    <span class="design-info"><strong>Forest</strong><span>Deep green and lime</span></span>
                </button>
                <button class="design-option" id="theme-swatch-royal" onclick="setDesignTheme('royal')">
                    <span class="design-preview preview-royal"></span>
                    <span class="design-info"><strong>Royal</strong><span>Purple, gold and sky blue</span></span>
                </button>
                <button class="design-option" id="theme-swatch-mono" onclick="setDesignTheme('mono')">
                    <span class="design-preview preview-mono"></span>
                    <span class="design-info"><strong>Mono</strong><span>Sharp black and white</span></span>
                </button>
                <button class="design-option" id="theme-swatch-kahoot" onclick="setDesignTheme('kahoot')">
                    <span class="design-preview preview-kahoot"></span>
                    <span class="design-info"><strong>Kahoot</strong><span>Purple stage, bold colourful tiles</span></span>
                </button>
            </div>
        </div>
    </div>

    <div id="history-page" class="hidden history-fullpage">
        <div class="history-fullpage-inner">
            <div class="history-page-topline">
                <button class="nav-arrow-btn" onclick="closeHistoryPage()" aria-label="Back">
                    <span class="icon-svg" id="history-back-icon"></span>
                </button>
                <h2 data-i18n="history_title">Test History</h2>
            </div>
            <div class="history-actions">
                <label class="history-select-all">
                    <input type="checkbox" id="history-select-all" onchange="toggleSelectAllHistory(this.checked)">
                    <span>Select all</span>
                </label>
                <button class="btn-history-delete" id="history-delete-selected" onclick="deleteCheckedHistory()" disabled><span data-i18n="delete">Delete</span> <span id="history-delete-count"></span></button>
            </div>
            <div class="history-list" id="history-list"></div>
        </div>
    </div>

    <!-- Confirm deleting history entries -->
    <div class="confirm-overlay hidden" id="history-delete-overlay" onclick="if(event.target===this) closeHistoryDeleteConfirm()">
        <div class="confirm-card">
            <h3 id="history-delete-title">Delete test result?</h3>
            <p class="confirm-subtext" id="history-delete-subtext">This cannot be undone.</p>
            <div class="confirm-actions">
                <button class="btn-confirm-cancel" onclick="closeHistoryDeleteConfirm()">Cancel</button>
                <button class="btn-confirm-yes" onclick="confirmHistoryDelete()">Delete</button>
            </div>
        </div>
    </div>

    <!-- Result window opened from a Test History block -->
    <div class="history-result-overlay hidden" id="history-result-overlay" onclick="if(event.target===this) closeHistoryResult()">
        <div class="history-result-card">
            <button class="modal-close" onclick="closeHistoryResult()" aria-label="Close"><span id="history-result-close-icon" class="icon-svg"></span></button>
            <div class="history-result-mode" id="hr-mode"></div>
            <div class="history-result-date" id="hr-date"></div>
            <div class="result-user">
                <strong id="hr-user-name"></strong>
                <small id="hr-user-email"></small>
            </div>
            <div class="correct-count-wrap" id="hr-count-wrap">
                <div class="correct-count-number" id="hr-count">0</div>
                <div class="correct-count-label">correct answers</div>
            </div>
            <p id="hr-score"></p>
            <p id="hr-essay-status"></p>
            <div class="history-result-actions">
                <button class="btn" onclick="openHistoryDetails()">Test Details</button>
                <button class="btn-glass hidden" id="hr-download-btn" onclick="downloadHistoryEssay()">
                    <span class="btn-glass-icon icon-svg" id="hr-download-icon"></span> <span>Download Essay</span>
                </button>
            </div>
        </div>
    </div>

    <div class="history-context-menu hidden" id="history-context-menu">
        <button onclick="deleteSelectedHistory()" data-i18n="delete">Delete</button>
    </div>

    <div class="container">
        <!-- Nickname -->
        <div id="nickname-screen">
            <h1 data-i18n="nickname_title">Kazakhstan History Quiz & Essay</h1>
            <p data-i18n="nickname_subtitle">Enter your nickname to begin</p>
            <input type="text" id="nickname-input" class="nickname-input" placeholder="Your nickname" data-i18n-placeholder="nickname_placeholder" maxlength="30" onkeydown="if(event.key==='Enter') confirmNickname()">
            <button class="btn" onclick="confirmNickname()" data-i18n="nickname_ready">READY!</button>
        </div>

        <!-- Start: mode carousel (Quiz / Essay / Weekly Test / Game) -->
        <div id="start-screen" class="hidden">
            <p id="welcome-text"></p>
            <div class="mode-carousel" id="mode-carousel">
                <button class="mode-arrow mode-arrow-left" onclick="rotateMode(-1)" aria-label="Previous mode"><span class="icon-svg" id="mode-prev-icon"></span></button>
                <div class="mode-stage" id="mode-stage">
                    <div class="mode-glow" aria-hidden="true"></div>
                    <div class="mode-shadow"></div>
                    <div class="mode-card" data-mode="quiz"><img src="assets/quiz.png" alt="Quiz" draggable="false"></div>
                    <div class="mode-card" data-mode="essay"><img src="assets/essay.png" alt="Essay" draggable="false"></div>
                    <div class="mode-card" data-mode="week"><img src="assets/week.png" alt="Weekly Test" draggable="false"></div>
                    <div class="mode-card" data-mode="game"><img src="assets/game.png" alt="Game" draggable="false"></div>
                </div>
                <button class="mode-arrow mode-arrow-right" onclick="rotateMode(1)" aria-label="Next mode"><span class="icon-svg" id="mode-next-icon"></span></button>
            </div>
            <div class="mode-name" id="mode-name" aria-live="polite">QUIZ</div>
            <button class="btn mode-start-btn" id="mode-start-btn" onclick="startSelectedMode()" data-i18n="mode_start">START</button>
            <div class="resume-note hidden" id="resume-note">
                <p>Your test was not finished. Continue?</p>
                <small id="resume-note-detail"></small>
                <div class="resume-actions">
                    <button class="btn" onclick="resumeUnfinished()">Continue</button>
                    <button class="btn btn-secondary" onclick="askDiscardUnfinished()">Delete</button>
                </div>
            </div>
        </div>

        <!-- Essay -->
        <div id="essay-screen" class="hidden">
            <h2 id="essay-title-text">Final Task: Historical Essay</h2>
            <div class="essay-prompt" id="essay-prompt-text">
                <strong>Topic:</strong> Analyze the role of the Saka tribes in shaping the political and cultural landscape of the Eurasian steppe. Write your arguments in English (around 120 words).
            </div>
            <textarea id="essay-input" class="essay-textarea" placeholder="Type your essay here (120 words)..."></textarea>
            <div class="essay-word-counter" id="essay-word-counter">0 / 120 words</div>
            <div class="essay-footer">
                <div class="essay-timer" id="essay-timer-display">Time left: 20:00</div>
                <button class="btn" onclick="submitEssay()" data-i18n="essay_submit">SUBMIT & SAVE ESSAY</button>
            </div>
        </div>

        <!-- Result -->
        <div id="result-screen" class="hidden">
            <div class="result-user" id="result-user">
                <strong id="result-user-name"></strong>
                <small id="result-user-email"></small>
            </div>
            <div class="correct-count-wrap">
                <div class="correct-count-number" id="correct-count-number">0</div>
                <div class="correct-count-label">correct answers</div>
            </div>
            <p id="final-score">Your score: 0</p>
            <p id="essay-status"></p>
            <button class="btn" onclick="restartQuiz()" data-i18n="result_play_again">PLAY AGAIN</button>
            <button class="btn-view-results" id="view-results-btn" onclick="openResultsReview()" data-i18n="result_view_result">View Result</button>
            <button class="btn-glass hidden" id="download-essay-btn" onclick="downloadEssay()">
                <span class="btn-glass-icon icon-svg" id="download-icon"></span> <span data-i18n="result_download_essay">Download Essay</span>
            </button>
        </div>
    </div>

    <div class="guard-warning hidden" id="guard-warning">
        <div class="guard-warning-card">
            <span class="guard-warning-icon icon-svg" id="guard-warning-icon"></span>
            <h3 id="guard-warning-title">Warning</h3>
            <p id="guard-warning-text"></p>
            <button class="btn-confirm-yes" onclick="closeGuardWarning()">OK</button>
        </div>
    </div>

    <!-- Finish-test button (quiz screen only) -->
    <button class="btn-finish-test hidden" id="finish-test-btn" onclick="openFinishConfirm()">Finish</button>

    <!-- Finish confirmation (site-styled, not a system confirm()) -->
    <div class="confirm-overlay hidden" id="finish-confirm-overlay">
        <div class="confirm-card">
            <h3>End the test?</h3>
            <p class="confirm-subtext" id="finish-confirm-subtext"></p>
            <div class="confirm-actions">
                <button class="btn-confirm-cancel" onclick="closeFinishConfirm()">Cancel</button>
                <button class="btn-confirm-yes" onclick="confirmFinishTest()">End Test</button>
            </div>
        </div>
    </div>

    <!-- Full test review (all questions + essay, each in its own block) -->
    <div class="results-review-overlay hidden" id="results-review-overlay">
        <div class="results-review-card">
            <button class="auth-close" onclick="closeResultsReview()" aria-label="Close">
                <span class="icon-svg" id="results-review-close-icon"></span>
            </button>
            <h2>Test Review</h2>
            <div class="results-review-list" id="results-review-list"></div>
        </div>
    </div>

    <!-- Quiz: full-page, no card -->
    <div id="quiz-screen" class="hidden quiz-fullpage">
        <div class="quiz-fullpage-inner">
            <div class="quiz-header">
                <button class="nav-arrow-btn" id="nav-back-btn" onclick="navigateBack()" aria-label="Previous question">
                    <span class="icon-svg" id="nav-back-icon"></span>
                </button>
                <div class="quiz-header-info">
                    <span id="question-counter">Question 1 / 90</span>
                    <span id="score-display">Score: 0</span>
                </div>
                <button class="nav-arrow-btn" id="nav-forward-btn" onclick="navigateForward()" aria-label="Next question">
                    <span class="icon-svg" id="nav-forward-icon"></span>
                </button>
            </div>
            <div class="timer-bar">
                <div id="timer-progress" class="timer-progress"></div>
            </div>
            <div id="question-text" class="question-box">Loading question...</div>
            <div class="answers-grid" id="answers-container">
                <!-- Answer buttons generated dynamically -->
            </div>
        </div>
    </div>

    <!-- Game lobby (Battlegrounds style): no back button - use the menu to leave -->
    <div id="lobby-page" class="hidden lobby-page">
        <div class="lobby-bg" aria-hidden="true"></div>

        <aside class="lobby-online" aria-label="Online users">
            <div class="lobby-online-head">
                <span class="lobby-online-dot" aria-hidden="true"></span>
                <strong>ONLINE</strong>
                <span id="lobby-online-count">0</span>
            </div>
            <div class="lobby-online-list" id="lobby-online-list">
                <div class="lobby-online-empty">Loading...</div>
            </div>
        </aside>

        <div class="lobby-invites" id="lobby-invites" aria-live="polite"></div>

        <div class="lobby-players" id="lobby-players" aria-live="polite"></div>

        <div class="lobby-panel" id="lobby-panel">
            <button class="lobby-type" id="lobby-type-btn" onclick="openLobbyTypes()">
                <img id="lobby-type-img" src="assets/answer-rush.png" alt="" draggable="false">
                <span class="lobby-type-text">
                    <small>GAME TYPE</small>
                    <strong id="lobby-type-name">ANSWER RUSH</strong>
                </span>
            </button>
            <div class="lobby-modes" id="lobby-modes" role="tablist" aria-label="Team size">
                <button class="lobby-mode active" data-size="1" onclick="setLobbyMode(1)">SOLO</button>
                <button class="lobby-mode" data-size="2" onclick="setLobbyMode(2)">DUO</button>
                <button class="lobby-mode" data-size="4" onclick="setLobbyMode(4)">SQUAD</button>
                <button class="lobby-ai on" id="lobby-ai" type="button" onclick="toggleLobbyAI()" aria-label="AI players">AI</button>
            </div>
            <button class="lobby-play" id="lobby-play" onclick="lobbyPlayClick()" aria-label="Play">
                <span class="lobby-play-idle">PLAY</span>
                <span class="lobby-play-search">
                    <span class="lobby-timer" id="lobby-timer">00:00</span>
                    <span class="lobby-play-label" id="lobby-play-label">FINDING PLAYERS</span>
                    <span class="lobby-cancel icon-svg" id="lobby-cancel-icon"></span>
                </span>
            </button>
        </div>

        <!-- Game-type picker: no card, same look as the home mode picker -->
        <div id="lobby-type-page" class="hidden lobby-type-page">
            <div class="lobby-bg" aria-hidden="true"></div>
            <div class="lobby-type-inner">
                <div class="mode-carousel">
                    <button class="mode-arrow mode-arrow-left" onclick="rotateLobbyType(-1)" aria-label="Previous type"><span class="icon-svg" id="lobby-prev-icon"></span></button>
                    <div class="mode-stage" id="lobby-type-stage">
                        <div class="mode-glow" aria-hidden="true"></div>
                        <div class="mode-shadow"></div>
                    </div>
                    <button class="mode-arrow mode-arrow-right" onclick="rotateLobbyType(1)" aria-label="Next type"><span class="icon-svg" id="lobby-next-icon"></span></button>
                </div>
                <div class="mode-name" id="lobby-type-title">ANSWER RUSH</div>
                <button class="btn mode-start-btn" onclick="selectLobbyType()">SELECT</button>
            </div>
        </div>
    </div>
    <script src="weekly-tests.js?v=1"></script>
    <script src="games-data.js?v=3"></script>
    <script src="games-net.js?v=2"></script>
    <script src="games-core.js?v=4"></script>
    <script src="games-a.js?v=3"></script>
    <script src="games-b.js?v=3"></script>
    <script src="mafia.js?v=1"></script>
    <script>
        /* 90 questions across 17 lecture topics (weeks 1-3: HK6002 History of Kazakhstan syllabus) */
        const fullQuizPool = JSON.parse(atob("W3sicXVlc3Rpb24iOiJXaGF0IHR5cGUgb2YgZXZpZGVuY2UgZG8gaGlzdG9yaWFucyBwcmltYXJpbHkgdXNlIHRvIHN0dWR5IEthemFraHN0YW4ncyBwcmVoaXN0b3JpYyBwYXN0PyIsImNvcnJlY3RBbnN3ZXIiOiJBcmNoYWVvbG9naWNhbCBmaW5kaW5ncyBhbmQgbWF0ZXJpYWwgY3VsdHVyZSIsIndyb25nQW5zd2VycyI6WyJPbmx5IHdyaXR0ZW4gY2hyb25pY2xlcyIsIk9ubHkgb3JhbCBmb2xrbG9yZSIsIk9ubHkgZm9yZWlnbiBkaXBsb21hdGljIHJlcG9ydHMiXX0seyJxdWVzdGlvbiI6IldoaWNoIHNjaWVudGlmaWMgZGlzY2lwbGluZSBzdHVkaWVzIGFuY2llbnQgaHVtYW4gcmVtYWlucyBhbmQgYXJ0aWZhY3RzIHRvIHJlY29uc3RydWN0IEthemFraHN0YW4ncyBwcmVoaXN0b3JpYyBwYXN0PyIsImNvcnJlY3RBbnN3ZXIiOiJBcmNoYWVvbG9neSIsIndyb25nQW5zd2VycyI6WyJTb2Npb2xvZ3kiLCJQb2xpdGljYWwgc2NpZW5jZSIsIkxpbmd1aXN0aWNzIl19LHsicXVlc3Rpb24iOiJUaGUgaGlzdG9yeSBvZiBLYXpha2hzdGFuIGlzIHRyYWRpdGlvbmFsbHkgc3R1ZGllZCB3aXRoaW4gd2hpY2ggYnJvYWRlciBnZW9ncmFwaGljYWwgY29udGV4dD8iLCJjb3JyZWN0QW5zd2VyIjoiVGhlIEV1cmFzaWFuIEdyZWF0IFN0ZXBwZSIsIndyb25nQW5zd2VycyI6WyJUaGUgQXJhYmlhbiBQZW5pbnN1bGEiLCJUaGUgTWVkaXRlcnJhbmVhbiBCYXNpbiIsIlRoZSBJbmRpYW4gc3ViY29udGluZW50Il19LHsicXVlc3Rpb24iOiJXaHkgaXMgYSBzb3VyY2UtY3JpdGljYWwsIGV2aWRlbmNlLWJhc2VkIGFwcHJvYWNoIGltcG9ydGFudCB3aGVuIHN0dWR5aW5nIEthemFraHN0YW4ncyBoaXN0b3J5PyIsImNvcnJlY3RBbnN3ZXIiOiJJdCBoZWxwcyBkaXN0aW5ndWlzaCB2ZXJpZmllZCBmYWN0cyBmcm9tIG15dGhzIGFuZCBwcm9wYWdhbmRhIiwid3JvbmdBbnN3ZXJzIjpbIkl0IG1ha2VzIHRoZSBoaXN0b3J5IHNob3J0ZXIiLCJJdCByZW1vdmVzIHRoZSBuZWVkIGZvciBhcmNoYWVvbG9neSIsIkl0IGZvY3VzZXMgb25seSBvbiByZWNlbnQgZXZlbnRzIl19LHsicXVlc3Rpb24iOiJXaGljaCBvZiB0aGUgZm9sbG93aW5nIGlzIGdlbmVyYWxseSBOT1QgY29uc2lkZXJlZCBhIHByaW1hcnkgaGlzdG9yaWNhbCBzb3VyY2U/IiwiY29ycmVjdEFuc3dlciI6IkEgbW9kZXJuIHRleHRib29rIHN1bW1hcnkiLCJ3cm9uZ0Fuc3dlcnMiOlsiQW4gYXJjaGFlb2xvZ2ljYWwgYXJ0aWZhY3QiLCJBbiBhbmNpZW50IGluc2NyaXB0aW9uIiwiQSBjb250ZW1wb3JhcnkgY2hyb25pY2xlIl19LHsicXVlc3Rpb24iOiJXaGljaCBlcmEgY29tZXMgaW1tZWRpYXRlbHkgYWZ0ZXIgdGhlIFN0b25lIEFnZSBpbiB0aGUgc3RhbmRhcmQgcGVyaW9kaXphdGlvbiBvZiBLYXpha2hzdGFuJ3MgaGlzdG9yeT8iLCJjb3JyZWN0QW5zd2VyIjoiVGhlIEJyb256ZSBBZ2UiLCJ3cm9uZ0Fuc3dlcnMiOlsiVGhlIElyb24gQWdlIiwiVGhlIFR1cmtpYyBlcmEiLCJUaGUgTWlncmF0aW9uIFBlcmlvZCJdfSx7InF1ZXN0aW9uIjoiV2hhdCBpcyB0aGUgdGVybSBmb3IgdGhlIGhpc3RvcmljYWwgcGVyaW9kIGNoYXJhY3Rlcml6ZWQgYnkgdGhlIHdpZGVzcHJlYWQgdXNlIG9mIHN0b25lIHRvb2xzPyIsImNvcnJlY3RBbnN3ZXIiOiJUaGUgU3RvbmUgQWdlIiwid3JvbmdBbnN3ZXJzIjpbIlRoZSBJcm9uIEFnZSIsIlRoZSBCcm9uemUgQWdlIiwiVGhlIFR1cmtpYyBlcmEiXX0seyJxdWVzdGlvbiI6IlRoZSBFYXJseSBJcm9uIEFnZSBpbiBLYXpha2hzdGFuIGlzIHByaW1hcmlseSBhc3NvY2lhdGVkIHdpdGggd2hpY2ggZ3JvdXAgb2YgcGVvcGxlcz8iLCJjb3JyZWN0QW5zd2VyIjoiVGhlIFNha2EgdHJpYmVzIiwid3JvbmdBbnN3ZXJzIjpbIlRoZSBNb25nb2xzIiwiVGhlIEthemFraCBLaGFuYXRlIiwiVGhlIEdvbGRlbiBIb3JkZSJdfSx7InF1ZXN0aW9uIjoiQXJjaGFlb2xvZ2lzdHMgZGl2aWRlIEthemFraHN0YW4ncyBwcmVoaXN0b3J5IG1haW5seSBiYXNlZCBvbiB3aGljaCBjcml0ZXJpb24/IiwiY29ycmVjdEFuc3dlciI6IlRoZSBtYXRlcmlhbHMgdXNlZCBmb3IgdG9vbHMgYW5kIHdlYXBvbnMiLCJ3cm9uZ0Fuc3dlcnMiOlsiVGhlIGxhbmd1YWdlcyBzcG9rZW4iLCJUaGUgcmVsaWdpb25zIHByYWN0aWNlZCIsIlRoZSBzeXN0ZW0gb2YgZ292ZXJubWVudCJdfSx7InF1ZXN0aW9uIjoiV2hpY2ggcGVyaW9kIGRpcmVjdGx5IHByZWNlZGVzIHRoZSBLYXpha2ggS2hhbmF0ZSBpbiB0aGUgc3RhbmRhcmQgcGVyaW9kaXphdGlvbj8iLCJjb3JyZWN0QW5zd2VyIjoiVGhlIEdvbGRlbiBIb3JkZSBwZXJpb2QiLCJ3cm9uZ0Fuc3dlcnMiOlsiVGhlIEJyb256ZSBBZ2UiLCJUaGUgU3RvbmUgQWdlIiwiVGhlIEVhcmx5IElyb24gQWdlIl19LHsicXVlc3Rpb24iOiJXaGF0IGFyZSB0aGUgZm91ciBtYWluIHN1YmRpdmlzaW9ucyBvZiB0aGUgU3RvbmUgQWdlIHJlY29nbml6ZWQgaW4gS2F6YWtoc3RhbidzIGhpc3Rvcnk/IiwiY29ycmVjdEFuc3dlciI6IlBhbGVvbGl0aGljLCBNZXNvbGl0aGljLCBOZW9saXRoaWMsIEVuZW9saXRoaWMiLCJ3cm9uZ0Fuc3dlcnMiOlsiUGFsZW9saXRoaWMsIEJyb256ZSwgSXJvbiwgVHVya2ljIiwiTWVzb2xpdGhpYywgTmVvbGl0aGljLCBJcm9uLCBCcm9uemUiLCJFbmVvbGl0aGljLCBCcm9uemUsIElyb24sIE1lZGlldmFsIl19LHsicXVlc3Rpb24iOiJXaGljaCBTdG9uZSBBZ2UgcGVyaW9kIGlzIGFsc28ga25vd24gYXMgdGhlICdPbGQgU3RvbmUgQWdlJz8iLCJjb3JyZWN0QW5zd2VyIjoiUGFsZW9saXRoaWMiLCJ3cm9uZ0Fuc3dlcnMiOlsiTmVvbGl0aGljIiwiTWVzb2xpdGhpYyIsIkVuZW9saXRoaWMiXX0seyJxdWVzdGlvbiI6IlRoZSBOZW9saXRoaWMgcGVyaW9kIGlzIGFsc28ga25vd24gYXMgd2hpY2ggb2YgdGhlIGZvbGxvd2luZz8iLCJjb3JyZWN0QW5zd2VyIjoiVGhlIE5ldyBTdG9uZSBBZ2UiLCJ3cm9uZ0Fuc3dlcnMiOlsiVGhlIENvcHBlciBBZ2UiLCJUaGUgTWlkZGxlIFN0b25lIEFnZSIsIlRoZSBJcm9uIEFnZSJdfSx7InF1ZXN0aW9uIjoiV2hhdCBkb2VzIHRoZSB0ZXJtICdFbmVvbGl0aGljJyByZWZlciB0bz8iLCJjb3JyZWN0QW5zd2VyIjoiVGhlIENvcHBlciBBZ2UgKHRyYW5zaXRpb24gYmV0d2VlbiBTdG9uZSBhbmQgQnJvbnplIEFnZXMpIiwid3JvbmdBbnN3ZXJzIjpbIlRoZSBmaW5hbCBzdGFnZSBvZiB0aGUgSXJvbiBBZ2UiLCJUaGUgYmVnaW5uaW5nIG9mIHRoZSBQYWxlb2xpdGhpYyIsIlRoZSByaXNlIG9mIG5vbWFkaWMgcGFzdG9yYWxpc20iXX0seyJxdWVzdGlvbiI6IlRoZSBNZXNvbGl0aGljIHBlcmlvZCBpcyBnZW5lcmFsbHkgdW5kZXJzdG9vZCBhcyBhIHRyYW5zaXRpb25hbCBzdGFnZSBiZXR3ZWVuIHdoaWNoIHR3byBwZXJpb2RzPyIsImNvcnJlY3RBbnN3ZXIiOiJUaGUgUGFsZW9saXRoaWMgYW5kIHRoZSBOZW9saXRoaWMiLCJ3cm9uZ0Fuc3dlcnMiOlsiVGhlIE5lb2xpdGhpYyBhbmQgdGhlIEJyb256ZSBBZ2UiLCJUaGUgQnJvbnplIEFnZSBhbmQgdGhlIElyb24gQWdlIiwiVGhlIEVuZW9saXRoaWMgYW5kIHRoZSBJcm9uIEFnZSJdfSx7InF1ZXN0aW9uIjoiU3RvbmUgQWdlIHNpdGVzIGluIEthemFraHN0YW4gcHJvdmlkZSBldmlkZW5jZSBwcmltYXJpbHkgdGhyb3VnaCB3aGljaCBraW5kIG9mIGZpbmRzPyIsImNvcnJlY3RBbnN3ZXIiOiJTdG9uZSB0b29scyBhbmQgdHJhY2VzIG9mIGVhcmx5IGh1bWFuIHNldHRsZW1lbnRzIiwid3JvbmdBbnN3ZXJzIjpbIldyaXR0ZW4gbWFudXNjcmlwdHMiLCJNaW50ZWQgY29pbnMiLCJTaWxrIHRleHRpbGVzIl19LHsicXVlc3Rpb24iOiJXaGF0IG1ham9yIGVjb25vbWljIGNoYW5nZSBpcyBhc3NvY2lhdGVkIHdpdGggdGhlICdOZW9saXRoaWMgUmV2b2x1dGlvbic/IiwiY29ycmVjdEFuc3dlciI6IlRoZSB0cmFuc2l0aW9uIGZyb20gaHVudGluZy1nYXRoZXJpbmcgdG8gZmFybWluZyBhbmQgYW5pbWFsIGh1c2JhbmRyeSIsIndyb25nQW5zd2VycyI6WyJUaGUgaW52ZW50aW9uIG9mIGlyb24gc21lbHRpbmciLCJUaGUgcmlzZSBvZiBub21hZGljIGhvcnNlIHdhcmZhcmUiLCJUaGUgZm91bmRpbmcgb2YgdGhlIGZpcnN0IGNpdGllcyBpbiBLYXpha2hzdGFuIl19LHsicXVlc3Rpb24iOiJXaHkgaXMgdGhlIE5lb2xpdGhpYyBSZXZvbHV0aW9uIGNvbnNpZGVyZWQgZ2xvYmFsbHkgc2lnbmlmaWNhbnQ/IiwiY29ycmVjdEFuc3dlciI6Ikl0IGxhaWQgdGhlIGZvdW5kYXRpb24gZm9yIHNldHRsZWQgYWdyaWN1bHR1cmFsIHNvY2lldGllcyIsIndyb25nQW5zd2VycyI6WyJJdCBlbmRlZCBhbGwgbm9tYWRpYyBsaWZlc3R5bGVzIHdvcmxkd2lkZSIsIkl0IGludHJvZHVjZWQgd3JpdGluZyBzeXN0ZW1zIGV2ZXJ5d2hlcmUiLCJJdCBjcmVhdGVkIHRoZSBmaXJzdCBnbG9iYWwgdHJhZGUgbmV0d29ya3MiXX0seyJxdWVzdGlvbiI6IkluIHRoZSBjb250ZXh0IG9mIEthemFraHN0YW4sIHRoZSBOZW9saXRoaWMgUmV2b2x1dGlvbiBpcyBjbG9zZWx5IGxpbmtlZCB0byB3aGljaCBsYXRlciBkZXZlbG9wbWVudD8iLCJjb3JyZWN0QW5zd2VyIjoiVGhlIGRvbWVzdGljYXRpb24gb2YgYW5pbWFscywgaW5jbHVkaW5nIHRoZSBob3JzZSIsIndyb25nQW5zd2VycyI6WyJUaGUgY29uc3RydWN0aW9uIG9mIFNpbGsgUm9hZCBjaXRpZXMiLCJUaGUgZm9ybWF0aW9uIG9mIHRoZSBLYXpha2ggS2hhbmF0ZSIsIlRoZSBzcHJlYWQgb2YgSXNsYW0iXX0seyJxdWVzdGlvbiI6IldoaWNoIG9mIHRoZSBmb2xsb3dpbmcgYmVzdCBkZXNjcmliZXMgYSBOZW9saXRoaWMtZXJhIHNldHRsZW1lbnQ/IiwiY29ycmVjdEFuc3dlciI6IkEgY29tbXVuaXR5IHJlbHlpbmcgaW5jcmVhc2luZ2x5IG9uIGZhcm1pbmcgYW5kIGhlcmRpbmcgcmF0aGVyIHRoYW4gb25seSBmb3JhZ2luZyIsIndyb25nQW5zd2VycyI6WyJBIHB1cmVseSBub21hZGljIHdhcnJpb3IgY2FtcCIsIkFuIGluZHVzdHJpYWwgbWluaW5nIHRvd24iLCJBIG1lZGlldmFsIHRyYWRpbmcgY2FyYXZhbnNlcmFpIl19LHsicXVlc3Rpb24iOiJUaGUgdGVybSAnTmVvbGl0aGljJyBpcyBtb3N0IGNsb3NlbHkgYXNzb2NpYXRlZCB3aXRoIHdoaWNoIGtpbmQgb2YgdG9vbHM/IiwiY29ycmVjdEFuc3dlciI6IlBvbGlzaGVkIHN0b25lIHRvb2xzIiwid3JvbmdBbnN3ZXJzIjpbIkJyb256ZSB3ZWFwb25zIiwiSXJvbiBwbG91Z2hzIiwiV292ZW4gc2lsayJdfSx7InF1ZXN0aW9uIjoiVGhlIEJvdGFpIGN1bHR1cmUgaW4gbm9ydGhlcm4gS2F6YWtoc3RhbiBpcyBmYW1vdXMgZm9yIGV2aWRlbmNlIG9mIHdoaWNoIGFjaGlldmVtZW50PyIsImNvcnJlY3RBbnN3ZXIiOiJPbmUgb2YgdGhlIGVhcmxpZXN0IGRvbWVzdGljYXRpb25zIG9mIHRoZSBob3JzZSIsIndyb25nQW5zd2VycyI6WyJUaGUgaW52ZW50aW9uIG9mIHRoZSB3aGVlbCIsIlRoZSBmaXJzdCB1c2Ugb2YgaXJvbiB0b29scyIsIlRoZSBlYXJsaWVzdCB3cml0aW5nIHN5c3RlbSBpbiBDZW50cmFsIEFzaWEiXX0seyJxdWVzdGlvbiI6IkluIHdoaWNoIHBhcnQgb2YgS2F6YWtoc3RhbiB3YXMgdGhlIEJvdGFpIGN1bHR1cmUgcHJpbWFyaWx5IGxvY2F0ZWQ/IiwiY29ycmVjdEFuc3dlciI6Ik5vcnRoZXJuIEthemFraHN0YW4iLCJ3cm9uZ0Fuc3dlcnMiOlsiU291dGhlcm4gS2F6YWtoc3RhbiIsIldlc3Rlcm4gS2F6YWtoc3RhbiAoQ2FzcGlhbiBjb2FzdCkiLCJFYXN0ZXJuIEthemFraHN0YW4gKEFsdGFpIG1vdW50YWlucykiXX0seyJxdWVzdGlvbiI6IlRvIHdoaWNoIGJyb2FkZXIgcGVyaW9kIGRvZXMgdGhlIEJvdGFpIGN1bHR1cmUgYmVsb25nPyIsImNvcnJlY3RBbnN3ZXIiOiJUaGUgRW5lb2xpdGhpYyAoQ29wcGVyIEFnZSkiLCJ3cm9uZ0Fuc3dlcnMiOlsiVGhlIElyb24gQWdlIiwiVGhlIEJyb256ZSBBZ2UiLCJUaGUgUGFsZW9saXRoaWMiXX0seyJxdWVzdGlvbiI6IldoeSBpcyBob3JzZSBkb21lc3RpY2F0aW9uIGF0IEJvdGFpIGNvbnNpZGVyZWQgZ2xvYmFsbHkgc2lnbmlmaWNhbnQ/IiwiY29ycmVjdEFuc3dlciI6Ikl0IHRyYW5zZm9ybWVkIHRyYW5zcG9ydGF0aW9uLCB3YXJmYXJlLCBhbmQgdGhlIHJpc2Ugb2Ygbm9tYWRpYyBwYXN0b3JhbGlzbSIsIndyb25nQW5zd2VycyI6WyJJdCBlbmRlZCB0aGUgbmVlZCBmb3IgYWdyaWN1bHR1cmUgZXZlcnl3aGVyZSIsIkl0IGNhdXNlZCB0aGUgY29sbGFwc2Ugb2YgdGhlIFN0b25lIEFnZSIsIkl0IGludHJvZHVjZWQgbWV0YWx3b3JraW5nIHRvIHRoZSBzdGVwcGUiXX0seyJxdWVzdGlvbiI6IldoYXQga2luZCBvZiBldmlkZW5jZSBkbyBhcmNoYWVvbG9naXN0cyB1c2UgdG8gYXJndWUgZm9yIGhvcnNlIGRvbWVzdGljYXRpb24gYXQgQm90YWkgc2l0ZXM/IiwiY29ycmVjdEFuc3dlciI6IkhvcnNlIGJvbmUgcmVtYWlucyBhbmQgdHJhY2VzIHN1Y2ggYXMgYml0LXdlYXIgb24gdGVldGgiLCJ3cm9uZ0Fuc3dlcnMiOlsiQW5jaWVudCB3cml0dGVuIGhvcnNlLWJyZWVkaW5nIG1hbnVhbHMiLCJDYXZlIHBhaW50aW5ncyBvZiBjaGFyaW90cyIsIlByZXNlcnZlZCBpcm9uIGhvcnNlc2hvZXMiXX0seyJxdWVzdGlvbiI6IldoaWNoIG1ham9yIEJyb256ZSBBZ2UgYXJjaGFlb2xvZ2ljYWwgY3VsdHVyZSBpcyBhc3NvY2lhdGVkIHdpdGggbXVjaCBvZiBLYXpha2hzdGFuIGFuZCB0aGUgd2lkZXIgc3RlcHBlPyIsImNvcnJlY3RBbnN3ZXIiOiJUaGUgQW5kcm9ub3ZvIGN1bHR1cmUiLCJ3cm9uZ0Fuc3dlcnMiOlsiVGhlIEJvdGFpIGN1bHR1cmUiLCJUaGUgS2FyYWtoYW5pZCBjdWx0dXJlIiwiVGhlIFNha2EgY3VsdHVyZSJdfSx7InF1ZXN0aW9uIjoiVGhlIEJlZ2F6eS1EYW5keWJhaSBjdWx0dXJlIGlzIGdlbmVyYWxseSBkYXRlZCB0byB3aGljaCBwYXJ0IG9mIHRoZSBCcm9uemUgQWdlPyIsImNvcnJlY3RBbnN3ZXIiOiJUaGUgTGF0ZSBCcm9uemUgQWdlIiwid3JvbmdBbnN3ZXJzIjpbIlRoZSBFYXJseSBQYWxlb2xpdGhpYyIsIlRoZSBFYXJseSBJcm9uIEFnZSIsIlRoZSBFbmVvbGl0aGljIl19LHsicXVlc3Rpb24iOiJCcm9uemUgQWdlIHNvY2lldGllcyBpbiBLYXpha2hzdGFuIGFyZSBwcmltYXJpbHkga25vd24gZm9yIGRldmVsb3Bpbmcgd2hpY2ggdGVjaG5vbG9neT8iLCJjb3JyZWN0QW5zd2VyIjoiQnJvbnplIG1ldGFsbHVyZ3kgKHRvb2xzIGFuZCB3ZWFwb25zIG1hZGUgb2YgYnJvbnplKSIsIndyb25nQW5zd2VycyI6WyJJcm9uIHNtZWx0aW5nIiwiU2lsayB3ZWF2aW5nIiwiUGFwZXJtYWtpbmciXX0seyJxdWVzdGlvbiI6IldoYXQga2luZCBvZiBlY29ub215IGNoYXJhY3Rlcml6ZWQgbW9zdCBBbmRyb25vdm8gY3VsdHVyZSBjb21tdW5pdGllcz8iLCJjb3JyZWN0QW5zd2VyIjoiQSBjb21iaW5hdGlvbiBvZiBwYXN0b3JhbGlzbSBhbmQgZWFybHkgYWdyaWN1bHR1cmUiLCJ3cm9uZ0Fuc3dlcnMiOlsiUHVyZWx5IG1hcml0aW1lIHRyYWRlIiwiSW5kdXN0cmlhbCBtaW5pbmcgZm9yIGV4cG9ydCIsIlVyYmFuIGNyYWZ0LWd1aWxkIGVjb25vbXkiXX0seyJxdWVzdGlvbiI6IkJlZ2F6eS1EYW5keWJhaSBzaXRlcyBhcmUgZXNwZWNpYWxseSBub3RhYmxlIGZvciB3aGljaCB0eXBlIG9mIGFyY2hhZW9sb2dpY2FsIHN0cnVjdHVyZXM/IiwiY29ycmVjdEFuc3dlciI6IkVsYWJvcmF0ZSBzdG9uZSBidXJpYWwgbW9udW1lbnRzIChtYXVzb2xldW1zKSIsIndyb25nQW5zd2VycyI6WyJVbmRlcmdyb3VuZCBpcnJpZ2F0aW9uIGNhbmFscyIsIkZvcnRpZmllZCBicmljayBjaXR5IHdhbGxzIiwiQnVkZGhpc3QgY2F2ZSB0ZW1wbGVzIl19LHsicXVlc3Rpb24iOiJUaGUgQW5kcm9ub3ZvIGN1bHR1cmUgaXMgbmFtZWQgYWZ0ZXIgYSBzaXRlIGxvY2F0ZWQgaW4gd2hpY2ggY291bnRyeT8iLCJjb3JyZWN0QW5zd2VyIjoiUnVzc2lhIChTaWJlcmlhKSIsIndyb25nQW5zd2VycyI6WyJLYXpha2hzdGFuIiwiTW9uZ29saWEiLCJDaGluYSJdfSx7InF1ZXN0aW9uIjoiV2hpY2ggZmFjdG9yIGlzIG1vc3QgY2xvc2VseSBsaW5rZWQgdG8gdGhlIHJpc2Ugb2Ygbm9tYWRpYyBjaXZpbGl6YXRpb24gaW4gdGhlIEthemFraCBzdGVwcGU/IiwiY29ycmVjdEFuc3dlciI6IlRoZSBkb21lc3RpY2F0aW9uIG9mIHRoZSBob3JzZSBhbmQgZGV2ZWxvcG1lbnQgb2YgbW9iaWxlIHBhc3RvcmFsaXNtIiwid3JvbmdBbnN3ZXJzIjpbIlRoZSBpbnZlbnRpb24gb2YgZ3VucG93ZGVyIiwiVGhlIHNwcmVhZCBvZiBCdWRkaGlzbSIsIlRoZSBjb25zdHJ1Y3Rpb24gb2YgcGVybWFuZW50IHN0b25lIGNpdGllcyJdfSx7InF1ZXN0aW9uIjoiTm9tYWRpYyBwYXN0b3JhbGlzbSBpbiB0aGUgc3RlcHBlIGlzIHByaW1hcmlseSBiYXNlZCBvbiB3aGljaCBlY29ub21pYyBhY3Rpdml0eT8iLCJjb3JyZWN0QW5zd2VyIjoiSGVyZGluZyBsaXZlc3RvY2sgc3VjaCBhcyBob3JzZXMsIHNoZWVwLCBhbmQgY2F0dGxlIGFjcm9zcyBzZWFzb25hbCBwYXN0dXJlcyIsIndyb25nQW5zd2VycyI6WyJZZWFyLXJvdW5kIGNyb3AgZmFybWluZyBpbiBmaXhlZCBmaWVsZHMiLCJEZWVwLXNlYSBmaXNoaW5nIiwiTGFyZ2Utc2NhbGUgbWluaW5nIGluZHVzdHJ5Il19LHsicXVlc3Rpb24iOiJXaGF0IGVudmlyb25tZW50YWwgZmVhdHVyZSBvZiBLYXpha2hzdGFuIG1hZGUgbm9tYWRpYyBwYXN0b3JhbGlzbSBhIHByYWN0aWNhbCB3YXkgb2YgbGlmZT8iLCJjb3JyZWN0QW5zd2VyIjoiVGhlIHZhc3Qgb3BlbiBzdGVwcGUgZ3Jhc3NsYW5kcyIsIndyb25nQW5zd2VycyI6WyJEZW5zZSB0cm9waWNhbCByYWluZm9yZXN0cyIsIkV4dGVuc2l2ZSBjb3JhbCByZWVmcyIsIkhpZ2gtYWx0aXR1ZGUgZ2xhY2llcnMiXX0seyJxdWVzdGlvbiI6Ik5vbWFkaWMgY2l2aWxpemF0aW9ucyBvZiB0aGUgc3RlcHBlIGFyZSBvZnRlbiBjcmVkaXRlZCB3aXRoIGFkdmFuY2luZyB3aGljaCBtaWxpdGFyeSB0ZWNobm9sb2d5PyIsImNvcnJlY3RBbnN3ZXIiOiJNb3VudGVkIGhvcnNlYmFjayB3YXJmYXJlIiwid3JvbmdBbnN3ZXJzIjpbIk5hdmFsIHNoaXBidWlsZGluZyIsIlBhcGVybWFraW5nIiwiR2xhc3NibG93aW5nIl19LHsicXVlc3Rpb24iOiJTZWFzb25hbCBtb3ZlbWVudCBvZiBub21hZHMgYmV0d2VlbiBwYXN0dXJlcyBpcyBrbm93biBieSB3aGljaCB0ZXJtPyIsImNvcnJlY3RBbnN3ZXIiOiJUcmFuc2h1bWFuY2UiLCJ3cm9uZ0Fuc3dlcnMiOlsiU2VkZW50YXJpemF0aW9uIiwiVXJiYW5pemF0aW9uIiwiQ29sbGVjdGl2aXphdGlvbiJdfSx7InF1ZXN0aW9uIjoiVGhlIFNha2EgdHJpYmVzIGFyZSBnZW5lcmFsbHkgY2xhc3NpZmllZCBhcyB3aGljaCB0eXBlIG9mIHNvY2lldHk/IiwiY29ycmVjdEFuc3dlciI6Ik5vbWFkaWMgSXJvbiBBZ2UgdHJpYmVzIG9mIHRoZSBFdXJhc2lhbiBzdGVwcGUiLCJ3cm9uZ0Fuc3dlcnMiOlsiQSBzZXR0bGVkIGFncmljdWx0dXJhbCBlbXBpcmUiLCJBIG1hcml0aW1lIHRyYWRpbmcgY2l2aWxpemF0aW9uIiwiQSBtZWRpZXZhbCBJc2xhbWljIHN0YXRlIl19LHsicXVlc3Rpb24iOiJJbiB3aGljaCBoaXN0b3JpY2FsIHBlcmlvZCBkaWQgdGhlIFNha2EgdHJpYmVzIGZsb3VyaXNoIGluIHRoZSB0ZXJyaXRvcnkgb2YgS2F6YWtoc3Rhbj8iLCJjb3JyZWN0QW5zd2VyIjoiVGhlIEVhcmx5IElyb24gQWdlICgxc3QgbWlsbGVubml1bSBCQykiLCJ3cm9uZ0Fuc3dlcnMiOlsiVGhlIEJyb256ZSBBZ2UiLCJUaGUgTWlkZGxlIEFnZXMiLCJUaGUgMTh0aCBjZW50dXJ5Il19LHsicXVlc3Rpb24iOiJUaGUgU2FrYSBlY29ub215IHdhcyBwcmltYXJpbHkgYmFzZWQgb24gd2hpY2ggYWN0aXZpdHk/IiwiY29ycmVjdEFuc3dlciI6Ik5vbWFkaWMgbGl2ZXN0b2NrIGhlcmRpbmciLCJ3cm9uZ0Fuc3dlcnMiOlsiTGFyZ2Utc2NhbGUgZ3JhaW4gZXhwb3J0IiwiRGVlcC1zZWEgdHJhZGUiLCJTaWxrIG1hbnVmYWN0dXJpbmciXX0seyJxdWVzdGlvbiI6IlNha2Egc29jaWV0eSBpcyBnZW5lcmFsbHkgZGVzY3JpYmVkIGJ5IGhpc3RvcmlhbnMgYXMgYmVpbmcgb3JnYW5pemVkIGFyb3VuZCB3aGF0IGtpbmQgb2YgbGVhZGVyc2hpcD8iLCJjb3JyZWN0QW5zd2VyIjoiVHJpYmFsIGNoaWVmcyBhbmQgd2FycmlvciBlbGl0ZXMiLCJ3cm9uZ0Fuc3dlcnMiOlsiQSBjZW50cmFsaXplZCBidXJlYXVjcmF0aWMgZW1waXJlIiwiQW4gZWxlY3RlZCBwYXJsaWFtZW50IiwiQSByZWxpZ2lvdXMgY2FsaXBoYXRlIl19LHsicXVlc3Rpb24iOiJXaGljaCBhbmNpZW50IGVtcGlyZSBpcyBrbm93biB0byBoYXZlIGhhZCBjb250YWN0IGFuZCBjb25mbGljdCB3aXRoIHRoZSBTYWthIHRyaWJlcz8iLCJjb3JyZWN0QW5zd2VyIjoiVGhlIEFjaGFlbWVuaWQgUGVyc2lhbiBFbXBpcmUiLCJ3cm9uZ0Fuc3dlcnMiOlsiVGhlIEF6dGVjIEVtcGlyZSIsIlRoZSBSb21hbiBSZXB1YmxpYyIsIlRoZSBJbmNhIEVtcGlyZSJdfSx7InF1ZXN0aW9uIjoiSW4gYW5jaWVudCBQZXJzaWFuIHNvdXJjZXMsIHRoZSB0ZXJtICdTYWthJyBnZW5lcmFsbHkgcmVmZXJyZWQgdG8gd2hpY2ggZ3JvdXA/IiwiY29ycmVjdEFuc3dlciI6IkVhc3Rlcm4gbm9tYWRpYyBzdGVwcGUgcGVvcGxlcyByZWxhdGVkIHRvIHRoZSBTY3l0aGlhbnMiLCJ3cm9uZ0Fuc3dlcnMiOlsiU2V0dGxlZCBmYXJtZXJzIG9mIE1lc29wb3RhbWlhIiwiU2FpbG9ycyBvZiB0aGUgTWVkaXRlcnJhbmVhbiIsIkNpdHktZHdlbGxlcnMgb2YgQ2VudHJhbCBDaGluYSJdfSx7InF1ZXN0aW9uIjoiU2FrYSBhcnQgaXMgZXNwZWNpYWxseSBmYW1vdXMgZm9yIHdoaWNoIGRpc3RpbmN0aXZlIHN0eWxlPyIsImNvcnJlY3RBbnN3ZXIiOiJUaGUgJ2FuaW1hbCBzdHlsZScsIGRlcGljdGluZyBzdHlsaXplZCBhbmltYWxzIGluIGdvbGQgYW5kIGJyb256ZSIsIndyb25nQW5zd2VycyI6WyJHZW9tZXRyaWMgbW9zYWljIHRpbGluZyIsIlJlYWxpc3RpYyBwb3J0cmFpdCBwYWludGluZyIsIkNhbGxpZ3JhcGhpYyBtYW51c2NyaXB0IGlsbHVtaW5hdGlvbiJdfSx7InF1ZXN0aW9uIjoiVGhlIGZhbW91cyAnR29sZGVuIE1hbicgKEFsdHluIEFkYW0pIGRpc2NvdmVyZWQgbmVhciBBbG1hdHkgaXMgYXNzb2NpYXRlZCB3aXRoIHdoaWNoIGN1bHR1cmU/IiwiY29ycmVjdEFuc3dlciI6IlRoZSBTYWthIGN1bHR1cmUiLCJ3cm9uZ0Fuc3dlcnMiOlsiVGhlIEFuZHJvbm92byBjdWx0dXJlIiwiVGhlIEthcmFraGFuaWQgY3VsdHVyZSIsIlRoZSBLYXpha2ggS2hhbmF0ZSJdfSx7InF1ZXN0aW9uIjoiU2FrYSBidXJpYWwgbW91bmRzLCBjb21tb24gYWNyb3NzIHRoZSBLYXpha2ggc3RlcHBlLCBhcmUga25vd24gYnkgd2hpY2ggdGVybT8iLCJjb3JyZWN0QW5zd2VyIjoiS3VyZ2FucyIsIndyb25nQW5zd2VycyI6WyJNYXVzb2xldW1zIiwiWmlnZ3VyYXRzIiwiUHlyYW1pZHMiXX0seyJxdWVzdGlvbiI6IldoYXQgZG8gU2FrYSBrdXJnYW4gYnVyaWFscyB0eXBpY2FsbHkgY29udGFpbiB0aGF0IHJldmVhbCB0aGVpciBiZWxpZWZzIGFuZCBzdGF0dXM/IiwiY29ycmVjdEFuc3dlciI6IlJpY2ggZ3JhdmUgZ29vZHMgc3VjaCBhcyBnb2xkIG9ybmFtZW50cyBhbmQgd2VhcG9ucyIsIndyb25nQW5zd2VycyI6WyJQcmludGVkIHJlbGlnaW91cyB0ZXh0cyIsIkNvaW5zIG1pbnRlZCB3aXRoIExhdGluIHNjcmlwdCIsIlBvcmNlbGFpbiBmcm9tIENoaW5hIl19LHsicXVlc3Rpb24iOiJUaGUgc3Bpcml0dWFsIGJlbGllZnMgb2YgdGhlIFNha2EgYXJlIGdlbmVyYWxseSBkZXNjcmliZWQgYnkgaGlzdG9yaWFucyBhcyBpbnZvbHZpbmcgd2hhdCBraW5kIG9mIHByYWN0aWNlcz8iLCJjb3JyZWN0QW5zd2VyIjoiTmF0dXJlIHdvcnNoaXAgYW5kIHNoYW1hbmlzdGljIHJpdHVhbHMiLCJ3cm9uZ0Fuc3dlcnMiOlsiQW4gb3JnYW5pemVkIG1vbm90aGVpc3RpYyByZWxpZ2lvbiB3aXRoIGEgd3JpdHRlbiBzY3JpcHR1cmUiLCJTdGF0ZS1lbmZvcmNlZCBhdGhlaXNtIiwiRm9ybWFsIEJ1ZGRoaXN0IG1vbmFzdGljIG9yZGVycyJdfSx7InF1ZXN0aW9uIjoiVGhlIEh1biBFbXBpcmUgaW4gQ2VudHJhbCBBc2lhIGlzIGdlbmVyYWxseSBjb25zaWRlcmVkIGEgY29udGludWF0aW9uIG9mIHdoaWNoIGVhcmxpZXIgc3RlcHBlIHRyYWRpdGlvbj8iLCJjb3JyZWN0QW5zd2VyIjoiTm9tYWRpYyBjb25mZWRlcmF0aW9ucyBvZiBtb3VudGVkIHdhcnJpb3JzIiwid3JvbmdBbnN3ZXJzIjpbIlNldHRsZWQgTWVkaXRlcnJhbmVhbiBjaXR5LXN0YXRlcyIsIkFncmljdWx0dXJhbCByaXZlci12YWxsZXkgY2l2aWxpemF0aW9ucyIsIk1hcml0aW1lIHRyYWRpbmcga2luZ2RvbXMiXX0seyJxdWVzdGlvbiI6IlRoZSBIdW5zIGFyZSBoaXN0b3JpY2FsbHkgc2lnbmlmaWNhbnQgZm9yIHRoZWlyIGltcGFjdCBvbiB3aGljaCBkaXN0YW50IHJlZ2lvbiBvZiB0aGUgd29ybGQ/IiwiY29ycmVjdEFuc3dlciI6IkV1cm9wZSwgd2hlcmUgdGhlaXIgd2VzdHdhcmQgbWlncmF0aW9uIGNvbnRyaWJ1dGVkIHRvIHRoZSBmYWxsIG9mIHRoZSBXZXN0ZXJuIFJvbWFuIEVtcGlyZSIsIndyb25nQW5zd2VycyI6WyJTb3V0aCBBbWVyaWNhIiwiU3ViLVNhaGFyYW4gQWZyaWNhIiwiQXVzdHJhbGlhIl19LHsicXVlc3Rpb24iOiJIdW4gcG9saXRpY2FsIG9yZ2FuaXphdGlvbiBpbiBDZW50cmFsIEFzaWEgd2FzIHR5cGljYWxseSBzdHJ1Y3R1cmVkIGFyb3VuZCB3aGF0PyIsImNvcnJlY3RBbnN3ZXIiOiJBIGNvbmZlZGVyYXRpb24gbGVkIGJ5IHBvd2VyZnVsIHRyaWJhbCBydWxlcnMgKHNoYW55dXMpIiwid3JvbmdBbnN3ZXJzIjpbIkEgcGFybGlhbWVudGFyeSByZXB1YmxpYyIsIkEgdGhlb2NyYXRpYyBwcmllc3Rob29kIiwiQSBtZXJjaGFudCBndWlsZCBjb3VuY2lsIl19LHsicXVlc3Rpb24iOiJXaGF0IG1pbGl0YXJ5IHRhY3RpYyB3ZXJlIHRoZSBIdW5zIHBhcnRpY3VsYXJseSBrbm93biBmb3I/IiwiY29ycmVjdEFuc3dlciI6Ik1vdW50ZWQgbW9iaWxlIHdhcmZhcmUgdXNpbmcgY29tcG9zaXRlIGJvd3MiLCJ3cm9uZ0Fuc3dlcnMiOlsiTmF2YWwgYmxvY2thZGVzIiwiVHJlbmNoIHdhcmZhcmUiLCJTaWVnZSBlbmdpbmVzIG1hZGUgb2YgaXJvbiJdfSx7InF1ZXN0aW9uIjoiVGhlIEh1biBFbXBpcmUncyBhY3Rpdml0eSBpbiBDZW50cmFsIEFzaWEgaXMgZ2VuZXJhbGx5IHBsYWNlZCBpbiB3aGljaCBicm9hZCBoaXN0b3JpY2FsIHBlcmlvZD8iLCJjb3JyZWN0QW5zd2VyIjoiVGhlIEVhcmx5IElyb24gQWdlIC8gbGF0ZSBhbnRpcXVpdHksIGFyb3VuZCB0aGUgMXN0IG1pbGxlbm5pdW0gQkMgdG8gZWFybHkgY2VudHVyaWVzIEFEIiwid3JvbmdBbnN3ZXJzIjpbIlRoZSBIaWdoIE1pZGRsZSBBZ2VzICgxMXRoLTEzdGggY2VudHVyeSkiLCJUaGUgQnJvbnplIEFnZSIsIlRoZSAxOHRoLTE5dGggY2VudHVyaWVzIl19LHsicXVlc3Rpb24iOiJUaGUgVXN1biB0cmliYWwgdW5pb24gd2FzIHByaW1hcmlseSBsb2NhdGVkIGluIHdoaWNoIHJlZ2lvbiBvZiBLYXpha2hzdGFuPyIsImNvcnJlY3RBbnN3ZXIiOiJTZW1pcmVjaHllIChaaGV0eXN1KSwgaW4gdGhlIHNvdXRoZWFzdCIsIndyb25nQW5zd2VycyI6WyJUaGUgQ2FzcGlhbiBsb3dsYW5kcyBpbiB0aGUgd2VzdCIsIlRoZSBBbHRhaSBtb3VudGFpbnMgaW4gdGhlIGZhciBlYXN0IiwiVGhlIFVyYWwgc3RlcHBlIGluIHRoZSBub3J0aCJdfSx7InF1ZXN0aW9uIjoiVGhlIEthbmdseSAoS2FuZ2p1KSBwb2xpdHkgaXMgaGlzdG9yaWNhbGx5IGFzc29jaWF0ZWQgd2l0aCB3aGljaCBwYXJ0IG9mIENlbnRyYWwgQXNpYT8iLCJjb3JyZWN0QW5zd2VyIjoiVGhlIFN5ciBEYXJ5YSByaXZlciBiYXNpbiIsIndyb25nQW5zd2VycyI6WyJUaGUgTmlsZSBEZWx0YSIsIlRoZSBZYW5ndHplIHJpdmVyIHZhbGxleSIsIlRoZSBJYmVyaWFuIFBlbmluc3VsYSJdfSx7InF1ZXN0aW9uIjoiVGhlIFNhcm1hdGlhbnMgYXJlIGdlbmVyYWxseSBkZXNjcmliZWQgYXMgcmVsYXRlZCB0byB3aGljaCBvdGhlciBzdGVwcGUgcGVvcGxlPyIsImNvcnJlY3RBbnN3ZXIiOiJUaGUgU2FrYS9TY3l0aGlhbnMiLCJ3cm9uZ0Fuc3dlcnMiOlsiVGhlIFZpa2luZ3MiLCJUaGUgTW9uZ29scyIsIlRoZSBBenRlY3MiXX0seyJxdWVzdGlvbiI6IlVzdW5zLCBLYW5nbHksIGFuZCBTYXJtYXRpYW5zIGFsbCBzaGFyZWQgd2hpY2ggY29tbW9uIGZlYXR1cmUgb2Ygc3RlcHBlIHNvY2lldGllcz8iLCJjb3JyZWN0QW5zd2VyIjoiQSBub21hZGljIG9yIHNlbWktbm9tYWRpYyBwYXN0b3JhbCBlY29ub215Iiwid3JvbmdBbnN3ZXJzIjpbIkFuIG9mZmljaWFsIHdyaXR0ZW4gbGFuZ3VhZ2UgaWRlbnRpY2FsIHRvIExhdGluIiwiUGVybWFuZW50IG1lbWJlcnNoaXAgaW4gdGhlIFJvbWFuIFNlbmF0ZSIsIkEgc2luZ2xlIHVuaWZpZWQgbW9uYXJjaHkgb3ZlciBhbGwgb2YgQXNpYSJdfSx7InF1ZXN0aW9uIjoiVGhlc2UgdHJpYmFsIHVuaW9ucyAoVXN1biwgS2FuZ2x5LCBTYXJtYXRpYW4pIHBsYXllZCBhbiBpbXBvcnRhbnQgcm9sZSBpbiB3aGljaCBicm9hZGVyIGhpc3RvcmljYWwgcHJvY2Vzcz8iLCJjb3JyZWN0QW5zd2VyIjoiVGhlIGV0aG5vZ2VuZXNpcyAoZm9ybWF0aW9uKSBvZiBwZW9wbGVzIGluIHRoZSBLYXpha2ggc3RlcHBlIiwid3JvbmdBbnN3ZXJzIjpbIlRoZSBmb3VuZGluZyBvZiB0aGUgT3R0b21hbiBFbXBpcmUiLCJUaGUgY29uc3RydWN0aW9uIG9mIHRoZSBHcmVhdCBXYWxsIG9mIENoaW5hIiwiVGhlIGNvbG9uaXphdGlvbiBvZiB0aGUgQW1lcmljYXMiXX0seyJxdWVzdGlvbiI6IlRoZSBFYXJseSBJcm9uIEFnZSBpbiBLYXpha2hzdGFuIGlzIHJvdWdobHkgZGF0ZWQgdG8gd2hpY2ggcGVyaW9kPyIsImNvcnJlY3RBbnN3ZXIiOiJUaGUgMXN0IG1pbGxlbm5pdW0gQkMiLCJ3cm9uZ0Fuc3dlcnMiOlsiVGhlIDFzdCBtaWxsZW5uaXVtIEFEIiwiVGhlIDNyZCBtaWxsZW5uaXVtIEJDIiwiVGhlIDE1dGggY2VudHVyeSBBRCJdfSx7InF1ZXN0aW9uIjoiV2hpY2ggdGVjaG5vbG9naWNhbCBjaGFuZ2UgZGVmaW5lcyB0aGUgc3RhcnQgb2YgdGhlIEVhcmx5IElyb24gQWdlPyIsImNvcnJlY3RBbnN3ZXIiOiJUaGUgd2lkZXNwcmVhZCB1c2Ugb2YgaXJvbiBmb3IgdG9vbHMgYW5kIHdlYXBvbnMiLCJ3cm9uZ0Fuc3dlcnMiOlsiVGhlIGludmVudGlvbiBvZiBndW5wb3dkZXIiLCJUaGUgZmlyc3QgdXNlIG9mIGJyb256ZSIsIlRoZSBpbnRyb2R1Y3Rpb24gb2Ygd3JpdGluZyBvbiBwYXBlciJdfSx7InF1ZXN0aW9uIjoiVGhlIEVhcmx5IElyb24gQWdlIGluIEthemFraHN0YW4gaXMgaGlzdG9yaWNhbGx5IGltcG9ydGFudCBtYWlubHkgYmVjYXVzZSBpdCBzYXcgdGhlIHJpc2Ugb2Ygd2hpY2gga2luZCBvZiBzb2NpZXRpZXM/IiwiY29ycmVjdEFuc3dlciI6Ik5vbWFkaWMgdHJpYmFsIGNvbmZlZGVyYXRpb25zIHN1Y2ggYXMgdGhlIFNha2EsIFVzdW4sIGFuZCBIdW4iLCJ3cm9uZ0Fuc3dlcnMiOlsiVGhlIGZpcnN0IElzbGFtaWMgY2FsaXBoYXRlcyIsIlRoZSBSdXNzaWFuIEVtcGlyZSdzIGNvbG9uaWFsIGFkbWluaXN0cmF0aW9uIiwiVGhlIFNvdmlldCBjb2xsZWN0aXZlIGZhcm1zIl19LHsicXVlc3Rpb24iOiJDb21wYXJlZCB0byB0aGUgQnJvbnplIEFnZSwgSXJvbiBBZ2UgdG9vbHMgYW5kIHdlYXBvbnMgd2VyZSBnZW5lcmFsbHkgd2hhdD8iLCJjb3JyZWN0QW5zd2VyIjoiU3Ryb25nZXIgYW5kIG1vcmUgZHVyYWJsZSIsIndyb25nQW5zd2VycyI6WyJXZWFrZXIgYW5kIG1vcmUgYnJpdHRsZSIsIk1hZGUgb25seSBmb3IgY2VyZW1vbmlhbCB1c2UiLCJDb21wbGV0ZWx5IGlkZW50aWNhbCBpbiBwcm9wZXJ0aWVzIl19LHsicXVlc3Rpb24iOiJUaGUgbm9tYWRpYyBjaXZpbGl6YXRpb24gdGhhdCBlbWVyZ2VkIGluIHRoZSBFYXJseSBJcm9uIEFnZSBpcyBjb25zaWRlcmVkIGZvdW5kYXRpb25hbCBiZWNhdXNlIGl0IGVzdGFibGlzaGVkIHdoYXQ/IiwiY29ycmVjdEFuc3dlciI6IkxvbmctbGFzdGluZyBwYXR0ZXJucyBvZiBzdGVwcGUgcGFzdG9yYWxpc20sIG1vYmlsZSB3YXJmYXJlLCBhbmQgY3VsdHVyYWwgZXhjaGFuZ2UiLCJ3cm9uZ0Fuc3dlcnMiOlsiVGhlIGZpcnN0IHBlcm1hbmVudCBzdG9uZSBjaXRpZXMgaW4gdGhlIHN0ZXBwZSIsIkEgdW5pZmllZCB3cml0dGVuIGNvbnN0aXR1dGlvbiIsIkEgc2luZ2xlIGdsb2JhbCBjdXJyZW5jeSJdfSx7InF1ZXN0aW9uIjoiSW4gd2hpY2ggY2VudHVyeSB3YXMgdGhlIEdyZWF0IFR1cmtpYyBLaGFnYW5hdGUgZXN0YWJsaXNoZWQ/IiwiY29ycmVjdEFuc3dlciI6IlRoZSA2dGggY2VudHVyeSBBRCIsIndyb25nQW5zd2VycyI6WyJUaGUgM3JkIGNlbnR1cnkgQkMiLCJUaGUgMTJ0aCBjZW50dXJ5IEFEIiwiVGhlIDE4dGggY2VudHVyeSBBRCJdfSx7InF1ZXN0aW9uIjoiVGhlIGZvdW5kZXJzIG9mIHRoZSBUdXJraWMgS2hhZ2FuYXRlIGJlbG9uZ2VkIHRvIHdoaWNoIHJ1bGluZyBjbGFuPyIsImNvcnJlY3RBbnN3ZXIiOiJUaGUgQXNoaW5hIGNsYW4iLCJ3cm9uZ0Fuc3dlcnMiOlsiVGhlIENoaW5nZ2lzaWQgY2xhbiIsIlRoZSBTZWxqdWsgZHluYXN0eSIsIlRoZSBUaW11cmlkIGR5bmFzdHkiXX0seyJxdWVzdGlvbiI6IlRoZSBUdXJraWMgS2hhZ2FuYXRlIGlzIGNvbnNpZGVyZWQgaGlzdG9yaWNhbGx5IGltcG9ydGFudCBtYWlubHkgYmVjYXVzZSBpdDoiLCJjb3JyZWN0QW5zd2VyIjoiVW5pdGVkIG1hbnkgVHVya2ljLXNwZWFraW5nIHRyaWJlcyB1bmRlciBvbmUgcG9saXRpY2FsIHN0cnVjdHVyZSBmb3IgdGhlIGZpcnN0IHRpbWUiLCJ3cm9uZ0Fuc3dlcnMiOlsiSW50cm9kdWNlZCBDaHJpc3RpYW5pdHkgYWNyb3NzIGFsbCBvZiBBc2lhIiwiV2FzIHRoZSBmaXJzdCBLYXpha2ggS2hhbmF0ZSIsIldhcyBwcmltYXJpbHkgYSBtYXJpdGltZSB0cmFkaW5nIGVtcGlyZSJdfSx7InF1ZXN0aW9uIjoiQXQgaXRzIGhlaWdodCwgdGhlIHRlcnJpdG9yeSBvZiB0aGUgVHVya2ljIEtoYWdhbmF0ZSBzdHJldGNoZWQgYWNyb3NzIHdoaWNoIHJlZ2lvbj8iLCJjb3JyZWN0QW5zd2VyIjoiRnJvbSBNYW5jaHVyaWEgdG8gdGhlIEJsYWNrIFNlYSBhY3Jvc3MgdGhlIEV1cmFzaWFuIHN0ZXBwZSIsIndyb25nQW5zd2VycyI6WyJPbmx5IHdpdGhpbiBtb2Rlcm4gS2F6YWtoc3RhbidzIGJvcmRlcnMiLCJPbmx5IHRoZSBBcmFiaWFuIFBlbmluc3VsYSIsIk9ubHkgdGhlIEphcGFuZXNlIGFyY2hpcGVsYWdvIl19LHsicXVlc3Rpb24iOiJUaGUgVHVya2ljIEtoYWdhbmF0ZSBldmVudHVhbGx5IHNwbGl0IGludG8gd2hpY2ggdHdvIG1ham9yIHBhcnRzPyIsImNvcnJlY3RBbnN3ZXIiOiJUaGUgRWFzdGVybiBhbmQgV2VzdGVybiBUdXJraWMgS2hhZ2FuYXRlcyIsIndyb25nQW5zd2VycyI6WyJUaGUgTm9ydGhlcm4gYW5kIFNvdXRoZXJuIFNvbmciLCJUaGUgQnl6YW50aW5lIGFuZCBTYXNzYW5pZCBlbXBpcmVzIiwiVGhlIEdvbGRlbiBhbmQgV2hpdGUgSG9yZGVzIl19LHsicXVlc3Rpb24iOiJSdWxlcnMgb2YgdGhlIFR1cmtpYyBLaGFnYW5hdGUgaGVsZCB3aGljaCB0aXRsZT8iLCJjb3JyZWN0QW5zd2VyIjoiS2FnYW4gKEtoYWdhbikiLCJ3cm9uZ0Fuc3dlcnMiOlsiUGhhcmFvaCIsIkNhZXNhciIsIlNoYWgtaW4tU2hhaCJdfSx7InF1ZXN0aW9uIjoiVGhlIFR1cmtpYyBLaGFnYW5hdGUgbWFpbnRhaW5lZCBkaXBsb21hdGljIGFuZCB0cmFkZSByZWxhdGlvbnMgd2l0aCB3aGljaCBDaHJpc3RpYW4gZW1waXJlIHRvIHRoZSB3ZXN0PyIsImNvcnJlY3RBbnN3ZXIiOiJUaGUgQnl6YW50aW5lIEVtcGlyZSIsIndyb25nQW5zd2VycyI6WyJUaGUgSG9seSBSb21hbiBFbXBpcmUiLCJUaGUgS2luZ2RvbSBvZiBGcmFuY2UiLCJUaGUgS2luZ2RvbSBvZiBFbmdsYW5kIl19LHsicXVlc3Rpb24iOiJUbyB0aGUgc291dGgsIHRoZSBUdXJraWMgS2hhZ2FuYXRlIGhhZCBzaWduaWZpY2FudCByZWxhdGlvbnMgd2l0aCB3aGljaCBQZXJzaWFuIGR5bmFzdHk/IiwiY29ycmVjdEFuc3dlciI6IlRoZSBTYXNzYW5pZCBFbXBpcmUiLCJ3cm9uZ0Fuc3dlcnMiOlsiVGhlIEFjaGFlbWVuaWQgRW1waXJlIiwiVGhlIFNhZmF2aWQgZHluYXN0eSIsIlRoZSBRYWphciBkeW5hc3R5Il19LHsicXVlc3Rpb24iOiJSZWxhdGlvbnMgYmV0d2VlbiB0aGUgVHVya2ljIEtoYWdhbmF0ZSBhbmQgQ2hpbmEgd2VyZSBvZnRlbiBjZW50ZXJlZCBhcm91bmQgY29udHJvbCBvZiB3aGljaCB0cmFkZSByb3V0ZT8iLCJjb3JyZWN0QW5zd2VyIjoiVGhlIFNpbGsgUm9hZCIsIndyb25nQW5zd2VycyI6WyJUaGUgQW1iZXIgUm9hZCIsIlRoZSBTcGljZSBSb3V0ZSBhY3Jvc3MgdGhlIEluZGlhbiBPY2VhbiIsIlRoZSBUcmFucy1TYWhhcmFuIHRyYWRlIHJvdXRlIl19LHsicXVlc3Rpb24iOiJUaGUgVHVya2ljIEtoYWdhbmF0ZSBhbmQgQnl6YW50aXVtIHNvbWV0aW1lcyBjb29wZXJhdGVkIGFnYWluc3Qgd2hpY2ggY29tbW9uIHJpdmFsPyIsImNvcnJlY3RBbnN3ZXIiOiJUaGUgU2Fzc2FuaWQgUGVyc2lhbiBFbXBpcmUiLCJ3cm9uZ0Fuc3dlcnMiOlsiVGhlIFJvbWFuIFJlcHVibGljIiwiVGhlIE1vbmdvbCBFbXBpcmUiLCJUaGUgS2F6YWtoIEtoYW5hdGUiXX0seyJxdWVzdGlvbiI6IkNoaW5lc2UgZHluYXN0aWMgcmVjb3JkcyBhcmUgYW4gaW1wb3J0YW50IGhpc3RvcmljYWwgc291cmNlIGZvciB0aGUgVHVya2ljIEtoYWdhbmF0ZSBiZWNhdXNlIHRoZXk6IiwiY29ycmVjdEFuc3dlciI6IkRvY3VtZW50IGRpcGxvbWF0aWMgbWlzc2lvbnMsIHRyYWRlLCBhbmQgY29uZmxpY3RzIHdpdGggdGhlIFR1cmtzIiwid3JvbmdBbnN3ZXJzIjpbIldlcmUgd3JpdHRlbiBieSB0aGUgVHVya3MgdGhlbXNlbHZlcyBpbiBMYXRpbiIsIkZvY3VzIG9ubHkgb24gS2F6YWtoc3RhbidzIEJyb256ZSBBZ2UiLCJEZXNjcmliZSBvbmx5IG1hcml0aW1lIHRyYWRlIGluIHRoZSBQYWNpZmljIl19LHsicXVlc3Rpb24iOiJUaGUgV2VzdGVybiBUdXJraWMgS2hhZ2FuYXRlIHdhcyBhbHNvIGtub3duIGJ5IHdoaWNoIG5hbWUsIHJlZmVycmluZyB0byBpdHMgdGVuIHJ1bGluZyB0cmliZXM/IiwiY29ycmVjdEFuc3dlciI6Ik9uLU9rICgnVGVuIEFycm93cycpIiwid3JvbmdBbnN3ZXJzIjpbIllhYmd1IENvbmZlZGVyYXRpb24iLCJPcmR1LUJhbGlrIExlYWd1ZSIsIlNhcnktQXJrYSBVbmlvbiJdfSx7InF1ZXN0aW9uIjoiVGhlIEJhdHRsZSBvZiBUYWxhcyAoNzUxIEFEKSB3YXMgZm91Z2h0IGJldHdlZW4gdGhlIEFyYWIgQWJiYXNpZCBDYWxpcGhhdGUgYW5kIHdoaWNoIHBvd2VyPyIsImNvcnJlY3RBbnN3ZXIiOiJUYW5nIER5bmFzdHkgQ2hpbmEiLCJ3cm9uZ0Fuc3dlcnMiOlsiVGhlIEJ5emFudGluZSBFbXBpcmUiLCJUaGUgTW9uZ29sIEVtcGlyZSIsIlRoZSBSdXNzaWFuIFRzYXJkb20iXX0seyJxdWVzdGlvbiI6IlRoZSBLYXJha2hhbmlkIGR5bmFzdHkgaXMgaGlzdG9yaWNhbGx5IHNpZ25pZmljYW50IGZvciBhZG9wdGluZyB3aGljaCByZWxpZ2lvbj8iLCJjb3JyZWN0QW5zd2VyIjoiSXNsYW0iLCJ3cm9uZ0Fuc3dlcnMiOlsiQnVkZGhpc20iLCJab3JvYXN0cmlhbmlzbSIsIk5lc3RvcmlhbiBDaHJpc3RpYW5pdHkiXX0seyJxdWVzdGlvbiI6IlRoZSBLaXBjaGFrIEtoYW5hdGUgaXMgYXNzb2NpYXRlZCB3aXRoIHdoaWNoIGhpc3RvcmljYWwgcmVnaW9uIG5hbWU/IiwiY29ycmVjdEFuc3dlciI6IkRhc2h0LWkgS2lwY2hhayAoJ0tpcGNoYWsgU3RlcHBlJykiLCJ3cm9uZ0Fuc3dlcnMiOlsiTWF3YXJhbm5haHIiLCJLaHdhcmV6bSIsIlRyYW5zb3hpYW5hIERlbHRhIl19LHsicXVlc3Rpb24iOiJUaGUgVHVyZ2VzaCBLaGFnYW5hdGUgcm9zZSB0byBwb3dlciBtYWlubHkgaW4gd2hpY2ggZm9ybWVyIHRlcnJpdG9yeT8iLCJjb3JyZWN0QW5zd2VyIjoiVGhlIGxhbmRzIG9mIHRoZSBmb3JtZXIgV2VzdGVybiBUdXJraWMgS2hhZ2FuYXRlIiwid3JvbmdBbnN3ZXJzIjpbIlRoZSBFYXN0ZXJuIFR1cmtpYyBLaGFnYW5hdGUncyBNb25nb2xpYW4gaGVhcnRsYW5kIiwiVGhlIEFyYWJpYW4gUGVuaW5zdWxhIiwiVGhlIEJ5emFudGluZSBCYWxrYW5zIl19LHsicXVlc3Rpb24iOiJUaGUgb3V0Y29tZSBvZiB0aGUgQmF0dGxlIG9mIFRhbGFzICg3NTEpIGlzIGhpc3RvcmljYWxseSBjcmVkaXRlZCB3aXRoIHNwcmVhZGluZyB3aGljaCB0ZWNobm9sb2d5IHdlc3R3YXJkPyIsImNvcnJlY3RBbnN3ZXIiOiJQYXBlcm1ha2luZyIsIndyb25nQW5zd2VycyI6WyJHdW5wb3dkZXIiLCJUaGUgY29tcGFzcyIsIlNpbGsgd2VhdmluZyJdfSx7InF1ZXN0aW9uIjoiVGhlIEdyZWF0IFNpbGsgUm9hZCBwcmltYXJpbHkgY29ubmVjdGVkIHdoaWNoIHR3byBlbmRzIG9mIEV1cmFzaWE/IiwiY29ycmVjdEFuc3dlciI6IkNoaW5hIGFuZCB0aGUgTWVkaXRlcnJhbmVhbi9FdXJvcGUiLCJ3cm9uZ0Fuc3dlcnMiOlsiSmFwYW4gYW5kIEF1c3RyYWxpYSIsIlNjYW5kaW5hdmlhIGFuZCBXZXN0IEFmcmljYSIsIkluZGlhIGFuZCBTb3V0aCBBbWVyaWNhIl19LHsicXVlc3Rpb24iOiJDaXRpZXMgaW4gc291dGhlcm4gS2F6YWtoc3Rhbiwgc3VjaCBhcyBPdHJhciBhbmQgVGFyYXosIGdyZXcgd2VhbHRoeSBtYWlubHkgZHVlIHRvIHRoZWlyIHJvbGUgYXM6IiwiY29ycmVjdEFuc3dlciI6IlRyYWRlIGFuZCBjYXJhdmFuIHN0b3BzIG9uIHRoZSBTaWxrIFJvYWQiLCJ3cm9uZ0Fuc3dlcnMiOlsiTmF2YWwgcG9ydHMgb24gdGhlIFBhY2lmaWMgT2NlYW4iLCJNaW5pbmcgY2VudGVycyBmb3Igb2lsIGV4dHJhY3Rpb24iLCJDYXBpdGFscyBvZiB0aGUgUm9tYW4gRW1waXJlIl19LHsicXVlc3Rpb24iOiJJbiBhZGRpdGlvbiB0byBzaWxrLCB3aGF0IGVsc2UgY29tbW9ubHkgdHJhdmVsZWQgYWxvbmcgdGhlIFNpbGsgUm9hZD8iLCJjb3JyZWN0QW5zd2VyIjoiSWRlYXMsIHJlbGlnaW9ucywgdGVjaG5vbG9naWVzLCBhbmQgb3RoZXIgZ29vZHMgc3VjaCBhcyBzcGljZXMgYW5kIG1ldGFscyIsIndyb25nQW5zd2VycyI6WyJPbmx5IHNpbGsgYW5kIG5vdGhpbmcgZWxzZSIsIk9ubHkgZ29sZCBjb2lucyIsIk9ubHkgZW5zbGF2ZWQgcGVvcGxlIl19LHsicXVlc3Rpb24iOiJUaGUgU2lsayBSb2FkIGNvbnRyaWJ1dGVkIHRvIENlbnRyYWwgQXNpYSdzIGRldmVsb3BtZW50IG1haW5seSBieSBmb3N0ZXJpbmc6IiwiY29ycmVjdEFuc3dlciI6IlVyYmFuaXphdGlvbiBhbmQgY3VsdHVyYWwvcmVsaWdpb3VzIGV4Y2hhbmdlIiwid3JvbmdBbnN3ZXJzIjpbIkNvbXBsZXRlIGlzb2xhdGlvbiBmcm9tIG5laWdoYm9yaW5nIHJlZ2lvbnMiLCJUaGUgZGVjbGluZSBvZiBhbGwgdHJhZGUgY2l0aWVzIiwiQSBwZXJtYW5lbnQgYmFuIG9uIGZvcmVpZ24gbWVyY2hhbnRzIl19LHsicXVlc3Rpb24iOiJXaGljaCBtb2RlIG9mIHRyYW5zcG9ydCB3YXMgbW9zdCBjb21tb25seSB1c2VkIGJ5IG1lcmNoYW50cyBhbG9uZyB0aGUgU2lsayBSb2FkIHRocm91Z2ggdGhlIHN0ZXBwZSBhbmQgZGVzZXJ0cz8iLCJjb3JyZWN0QW5zd2VyIjoiQ2FtZWwgYW5kIGhvcnNlIGNhcmF2YW5zIiwid3JvbmdBbnN3ZXJzIjpbIlN0ZWFtIGxvY29tb3RpdmVzIiwiT2NlYW4tZ29pbmcgZ2FsbGVvbnMiLCJBdXRvbW9iaWxlcyJdfSx7InF1ZXN0aW9uIjoiVGhlIHNwcmVhZCBvZiBJc2xhbSBpbiBDZW50cmFsIEFzaWEsIGVzcGVjaWFsbHkgdW5kZXIgdGhlIEthcmFraGFuaWRzLCBjb250cmlidXRlZCB0byBhIGZsb3VyaXNoaW5nIG9mIHdoaWNoIGZpZWxkPyIsImNvcnJlY3RBbnN3ZXIiOiJTY2llbmNlLCBwaGlsb3NvcGh5LCBhbmQgc2Nob2xhcnNoaXAgKGEgTXVzbGltL0lzbGFtaWMgcmVuYWlzc2FuY2UpIiwid3JvbmdBbnN3ZXJzIjpbIk9ubHkgbWlsaXRhcnkgY29ucXVlc3Qgd2l0aCBubyBjdWx0dXJhbCBkZXZlbG9wbWVudCIsIlRoZSBhYmFuZG9ubWVudCBvZiBhbGwgcHJldmlvdXMgbGVhcm5pbmciLCJFeGNsdXNpdmUgZm9jdXMgb24gbm9tYWRpYyBwYXN0b3JhbGlzbSJdfSx7InF1ZXN0aW9uIjoiV2hpY2ggdHlwZSBvZiBpbnN0aXR1dGlvbiBiZWNhbWUgY2VudGVycyBvZiBsZWFybmluZyBpbiBDZW50cmFsIEFzaWFuIGNpdGllcyBkdXJpbmcgdGhpcyBwZXJpb2Q/IiwiY29ycmVjdEFuc3dlciI6Ik1hZHJhc2FzIChJc2xhbWljIHNjaG9vbHMpIiwid3JvbmdBbnN3ZXJzIjpbIkNocmlzdGlhbiBtb25hc3RlcmllcyIsIkJ1ZGRoaXN0IHN0dXBhcyIsIlJvbWFuIGZvcnVtcyJdfSx7InF1ZXN0aW9uIjoiQ2VudHJhbCBBc2lhbiBhbmQgVHVya2ljIHNjaG9sYXJzIG9mIHRoaXMgZXJhIG1hZGUgbm90YWJsZSBjb250cmlidXRpb25zIHRvIHdoaWNoIGZpZWxkcz8iLCJjb3JyZWN0QW5zd2VyIjoiTWF0aGVtYXRpY3MsIGFzdHJvbm9teSwgYW5kIG1lZGljaW5lIiwid3JvbmdBbnN3ZXJzIjpbIk9ubHkgcG9ldHJ5LCB3aXRoIG5vIHNjaWVudGlmaWMgd29yayIsIk9ubHkgbWlsaXRhcnkgc3RyYXRlZ3kiLCJPbmx5IGFncmljdWx0dXJhbCB0ZWNobmlxdWVzIl19LHsicXVlc3Rpb24iOiJUaGUgZ3Jvd3RoIG9mIGNpdGllcyBhbG9uZyB0aGUgU2lsayBSb2FkIGluIHNvdXRoZXJuIEthemFraHN0YW4gc3VwcG9ydGVkIHRoZSBNdXNsaW0gUmVuYWlzc2FuY2UgbWFpbmx5IGJ5IHByb3ZpZGluZzoiLCJjb3JyZWN0QW5zd2VyIjoiV2VhbHRoLCB0cmFkZSBjb25uZWN0aW9ucywgYW5kIGh1YnMgZm9yIHNjaG9sYXJzIHRvIGdhdGhlciIsIndyb25nQW5zd2VycyI6WyJJc29sYXRpb24gZnJvbSBhbGwgb3V0c2lkZSBjb250YWN0IiwiQSBiYW4gb24gd3JpdHRlbiB0ZXh0cyIsIlB1cmVseSBub21hZGljIHBvcHVsYXRpb25zIHdpdGggbm8gY2l0aWVzIl19LHsicXVlc3Rpb24iOiJUaGUgdGVybSAnTXVzbGltIFJlbmFpc3NhbmNlJyBpbiB0aGlzIGNvbnRleHQgcHJpbWFyaWx5IHJlZmVycyB0byBhIGZsb3VyaXNoaW5nIG9mIGxlYXJuaW5nIHdpdGhpbiB3aGljaCBmcmFtZXdvcms/IiwiY29ycmVjdEFuc3dlciI6IlRoZSBJc2xhbWljIHdvcmxkIG9mIENlbnRyYWwgQXNpYSIsIndyb25nQW5zd2VycyI6WyJUaGUgQ2hyaXN0aWFuIHdvcmxkIG9mIFdlc3Rlcm4gRXVyb3BlIiwiVGhlIEJ1ZGRoaXN0IHdvcmxkIG9mIEVhc3QgQXNpYSIsIlRoZSBwcmUtSXNsYW1pYyBTYWthIHJlbGlnaW9uIl19XQ=="));

        function shuffleArray(arr) {
            const a = arr.slice();
            for (let i = a.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [a[i], a[j]] = [a[j], a[i]];
            }
            return a;
        }

        let quizDatabase = fullQuizPool;

        let currentQuestionIndex = 0;
        let score = 0;
        let timer;
        const TIME_PER_QUESTION = 15;
        let timeLeft = TIME_PER_QUESTION;
        let questionRenderState = [];
        let reviewIndex = null;

        let essayTimer;
        let essayTimeLeft = 20 * 60;
        let userEssayText = "";
        let essaySubmitted = false;
        let currentUserRole = 'guest';
                let menuOpen = false;
        let authStatusReady = false;

        const nicknameScreen = document.getElementById('nickname-screen');
        const nicknameInput = document.getElementById('nickname-input');
        const welcomeText = document.getElementById('welcome-text');
        const startScreen = document.getElementById('start-screen');
        const quizScreen = document.getElementById('quiz-screen');
        const essayScreen = document.getElementById('essay-screen');
        const resultScreen = document.getElementById('result-screen');

        const accountBar = document.getElementById('account-bar');
        const joinBtn = document.getElementById('join-btn');
        const accountInfo = document.getElementById('account-info');
        const accountEmailDisplay = document.getElementById('account-email-display');
        const authOverlay = document.getElementById('auth-overlay');
        const authChoiceScreen = document.getElementById('auth-choice-screen');
        const authSigninScreen = document.getElementById('auth-signin-screen');
        const authSignupScreen = document.getElementById('auth-signup-screen');

        function isTypingField(el) {
            return el && (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.isContentEditable);
        }

        function canAdminCopyText() {
            return currentUserRole === 'admin' && adminCopyEnabled;
        }

        document.addEventListener('contextmenu', e => {
            const historyRow = e.target.closest && e.target.closest('.history-row');
            if (historyRow) return;
            e.preventDefault();
            hideHistoryContextMenu();
        });
        document.addEventListener('copy', e => {
            if (canAdminCopyText()) return;
            e.preventDefault();
            if (isTestGuardActive()) registerGuardViolation('copy');
        });
        document.addEventListener('cut', e => {
            if (canAdminCopyText()) return;
            e.preventDefault();
            if (isTestGuardActive()) registerGuardViolation('copy');
        });
        document.addEventListener('selectstart', e => {
            if (canAdminCopyText()) return;
            if (!isTypingField(e.target)) e.preventDefault();
        });
        document.addEventListener('dragstart', e => e.preventDefault());

        document.addEventListener('wheel', e => {
            if (e.ctrlKey || e.metaKey) {
                e.preventDefault();
                if (isTestGuardActive()) registerGuardViolation('zoom');
            }
        }, { passive: false });
        document.addEventListener('keydown', e => {
            const key = e.key.toLowerCase();
            if ((e.ctrlKey || e.metaKey) && ['+', '-', '=', '0', 'add', 'subtract'].includes(e.key.toLowerCase())) {
                e.preventDefault();
                if (isTestGuardActive()) registerGuardViolation('zoom');
                return;
            }
            if ((e.ctrlKey || e.metaKey) && ['c', 'x', 'a', 's', 'p'].includes(key) && !isTypingField(e.target)) {
                if (canAdminCopyText() && ['c', 'a'].includes(key)) return;
                e.preventDefault();
            }
        });
        ['gesturestart', 'gesturechange', 'gestureend'].forEach(eventName => {
            document.addEventListener(eventName, e => e.preventDefault(), { passive: false });
        });
        /* Two-finger pinch and double-tap zoom (iOS Safari ignores user-scalable=no) */
        document.addEventListener('touchmove', e => {
            if (e.touches.length > 1) e.preventDefault();
        }, { passive: false });
        let lastTouchEndAt = 0;
        document.addEventListener('touchend', e => {
            const now = Date.now();
            const onControl = e.target.closest && e.target.closest('button, a, input, textarea, select, label, .mode-card, .wheel-scroll, .history-row');
            if (now - lastTouchEndAt < 320 && !onControl) e.preventDefault();
            lastTouchEndAt = now;
        }, { passive: false });
        document.addEventListener('dblclick', e => {
            if (canAdminCopyText() || isTypingField(e.target)) return;
            e.preventDefault();
        });

        /* Best-effort block of View Source / DevTools shortcuts. Modern Chrome/Firefox
           reserve these and ignore preventDefault(), so this only helps in some browsers —
           the real protection against reading questions is the base64 encoding below, not this. */
        document.addEventListener('keydown', e => {
            const key = e.key.toLowerCase();
            if (!isTestGuardActive()) return;
            if (key === 'f12') { e.preventDefault(); registerGuardViolation('tools'); return; }
            if ((e.ctrlKey || e.metaKey) && key === 'u') { e.preventDefault(); registerGuardViolation('tools'); return; }
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && ['i', 'j', 'c'].includes(key)) {
                e.preventDefault();
                registerGuardViolation('tools');
            }
        });

        const questionText = document.getElementById('question-text');
        const answersContainer = document.getElementById('answers-container');
        const questionCounter = document.getElementById('question-counter');
        const scoreDisplay = document.getElementById('score-display');
        const timerProgress = document.getElementById('timer-progress');
        
        const essayTimerDisplay = document.getElementById('essay-timer-display');
        const essayInput = document.getElementById('essay-input');
        const essayWordCounter = document.getElementById('essay-word-counter');
        const finalScore = document.getElementById('final-score');
        const correctCountNumber = document.getElementById('correct-count-number');
        const essayStatus = document.getElementById('essay-status');
        const downloadEssayBtn = document.getElementById('download-essay-btn');
        const menuPanel = document.getElementById('menu-panel');
        const menuBtn = document.getElementById('menu-btn');
        const muteIcon = document.getElementById('mute-icon');
        const menuMuteIcon = document.getElementById('menu-mute-icon');
        const designOverlay = document.getElementById('design-overlay');
        const essayPromptText = document.getElementById('essay-prompt-text');
        const navBackBtn = document.getElementById('nav-back-btn');
        const navForwardBtn = document.getElementById('nav-forward-btn');
        const finishTestBtn = document.getElementById('finish-test-btn');
        const finishConfirmOverlay = document.getElementById('finish-confirm-overlay');
        const finishConfirmSubtext = document.getElementById('finish-confirm-subtext');
        const resultsReviewOverlay = document.getElementById('results-review-overlay');
        const resultsReviewList = document.getElementById('results-review-list');
        const adminPanelMenuBtn = document.getElementById('admin-panel-menu-btn');
        const promoOverlay = document.getElementById('promo-overlay');
        const promoInput = document.getElementById('promo-input');
        const promoError = document.getElementById('promo-error');
        const adminPanelPage = document.getElementById('admin-panel-page');
        const adminSettingsOverlay = document.getElementById('admin-settings-overlay');
        const toggleShowAnswersBtn = document.getElementById('toggle-show-answers-btn');
        const toggleAdminCopyBtn = document.getElementById('toggle-admin-copy-btn');
        const usersPage = document.getElementById('users-page');
        const usersListEl = document.getElementById('users-list');
        const usersSearchInput = document.getElementById('users-search-input');
        const registeredCountEl = document.getElementById('registered-count');
        const adminCountEl = document.getElementById('admin-count');
        const guestCountEl = document.getElementById('guest-count');
        const tabUsersBtn = document.getElementById('tab-users-btn');
        const tabAdminsBtn = document.getElementById('tab-admins-btn');
        const tabGuestsBtn = document.getElementById('tab-guests-btn');
        const toggleTestGuardBtn = document.getElementById('toggle-test-guard-btn');
        const historyPage = document.getElementById('history-page');
        const historyMenuBtn = document.getElementById('history-menu-btn');
        const historyList = document.getElementById('history-list');
        const historySelectAll = document.getElementById('history-select-all');
        const historyContextMenu = document.getElementById('history-context-menu');
        const guardWarning = document.getElementById('guard-warning');
        const guardWarningIcon = document.getElementById('guard-warning-icon');
        const guardWarningTitle = document.getElementById('guard-warning-title');
        const guardWarningText = document.getElementById('guard-warning-text');

        const TEST_GUARD_KEY = 'testGuardEnabled';
        const MAX_GUARD_WARNINGS = 3;
        let testGuardEnabled = true;
        let testGuardRunning = false;
        let guardWarningCount = 0;
        let lastGuardViolationAt = 0;
        let pendingVisibilityWarning = false;
        let quizEndStatus = 'completed';

        function isTestGuardActive() {
            return testGuardEnabled && testGuardRunning;
        }

        let currentEssayTopic = '';
        function showEssayTopic(topic) {
            currentEssayTopic = topic;
            essayPromptText.innerHTML = `<strong>Topic:</strong> ${escapeHtml(topic)} Write your arguments in English (around 120 words).`;
        }

        const ESSAY_WORD_TARGET = 120;

        function countWords(text) {
            const trimmed = text.trim();
            return trimmed.length === 0 ? 0 : trimmed.split(/\s+/).length;
        }

        function updateWordCounter() {
            const count = countWords(essayInput.value);
            essayWordCounter.innerText = `${count} / ${ESSAY_WORD_TARGET} words`;
            essayWordCounter.classList.toggle('reached', count >= ESSAY_WORD_TARGET);
        }
        essayInput.addEventListener('input', updateWordCounter);

        /* Icon-only button graphics (no emoji), colored via currentColor so they follow the button's theme color */
        const ICON_SPEAKER = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><path d="M15.54 8.46a5 5 0 0 1 0 7.07"></path><path d="M19.07 4.93a10 10 0 0 1 0 14.14"></path></svg>';
        const ICON_SPEAKER_MUTED = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><line x1="23" y1="9" x2="17" y2="15"></line><line x1="17" y1="9" x2="23" y2="15"></line></svg>';
        const ICON_RESTART = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path></svg>';
        const ICON_HOME = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11.5 12 4l9 7.5"></path><path d="M5.5 10.5V20h13v-9.5"></path><path d="M9.5 20v-6h5v6"></path></svg>';
        const ICON_DOWNLOAD = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>';
        const ICON_RENAME = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>';
        const ICON_CHECK = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
        const ICON_X = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
        const ICON_LOGOUT = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>';
        const ICON_CHEVRON_LEFT = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>';
        const ICON_CHEVRON_RIGHT = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>';
        const ICON_TAG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41 11 3.83A2 2 0 0 0 9.59 3.24L4 3v5.59a2 2 0 0 0 .59 1.41l9.59 9.59a2 2 0 0 0 2.82 0l3.59-3.59a2 2 0 0 0 0-2.82z"></path><circle cx="8.5" cy="8.5" r="1.5"></circle></svg>';
        const ICON_PALETTE = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="13.5" cy="6.5" r=".7"></circle><circle cx="17.5" cy="10.5" r=".7"></circle><circle cx="8.5" cy="7.5" r=".7"></circle><circle cx="6.5" cy="12.5" r=".7"></circle><path d="M12 3a9 9 0 0 0 0 18h1.5a2 2 0 0 0 1.6-3.2 1.7 1.7 0 0 1 1.3-2.8H18a3 3 0 0 0 3-3c0-5-4-9-9-9Z"></path></svg>';
        const ICON_SHIELD = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>';
        const ICON_MUSIC = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l11-2v13"></path><circle cx="6" cy="18" r="3"></circle><circle cx="17" cy="16" r="3"></circle></svg>';
        const ICON_HISTORY = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 3-6.7"></path><polyline points="3 3 3 9 9 9"></polyline><path d="M12 7v5l3 2"></path></svg>';
        const ICON_TRASH = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"></path></svg>';
        const ICON_WARNING = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>';

        document.getElementById('home-icon').innerHTML = ICON_HOME;
        document.getElementById('music-icon').innerHTML = ICON_MUSIC;
        document.getElementById('rename-icon').innerHTML = ICON_RENAME;
        document.getElementById('music-close-icon').innerHTML = ICON_X;
        document.getElementById('download-icon').innerHTML = ICON_DOWNLOAD;
        document.getElementById('design-icon').innerHTML = ICON_PALETTE;
        document.getElementById('design-close-icon').innerHTML = ICON_X;
        document.getElementById('logout-icon').innerHTML = ICON_LOGOUT;
        document.getElementById('auth-close-icon').innerHTML = ICON_X;
        document.getElementById('nav-back-icon').innerHTML = ICON_CHEVRON_LEFT;
        document.getElementById('nav-forward-icon').innerHTML = ICON_CHEVRON_RIGHT;
        document.getElementById('results-review-close-icon').innerHTML = ICON_X;
        document.getElementById('admin-settings-close-icon').innerHTML = ICON_X;
        document.getElementById('admin-back-icon').innerHTML = ICON_CHEVRON_LEFT;
        document.getElementById('users-back-icon').innerHTML = ICON_CHEVRON_LEFT;
        document.getElementById('promo-icon').innerHTML = ICON_TAG;
        document.getElementById('admin-panel-icon').innerHTML = ICON_SHIELD;
        document.getElementById('history-icon').innerHTML = ICON_HISTORY;
        document.getElementById('mode-prev-icon').innerHTML = ICON_CHEVRON_LEFT;
        document.getElementById('mode-next-icon').innerHTML = ICON_CHEVRON_RIGHT;
        document.getElementById('week-picker-close-icon').innerHTML = ICON_X;
        document.getElementById('history-result-close-icon').innerHTML = ICON_X;
        document.getElementById('hr-download-icon').innerHTML = ICON_DOWNLOAD;
        document.getElementById('history-back-icon').innerHTML = ICON_CHEVRON_LEFT;
        guardWarningIcon.innerHTML = ICON_WARNING;
        muteIcon.innerHTML = ICON_SPEAKER;
        if (menuMuteIcon) menuMuteIcon.innerHTML = ICON_SPEAKER;

        /* Nickname: entered once, saved locally, editable any time via the menu */
        const NICKNAME_KEY = 'quizNickname';

        function getSavedNickname() {
            try { return localStorage.getItem(NICKNAME_KEY) || ''; } catch (e) { return ''; }
        }

        function saveNickname(name) {
            try { localStorage.setItem(NICKNAME_KEY, name); } catch (e) {}
        }

        function updateWelcomeText() {
            const name = getSavedNickname();
            welcomeText.innerText = name ? `Welcome, ${name}!` : '';
        }

        function showNicknameScreen() {
            nicknameScreen.classList.remove('hidden');
            startScreen.classList.add('hidden');
            syncAccountBarVisibility();
            nicknameInput.value = getSavedNickname();
            nicknameInput.focus();
        }

        function trackGuestNickname(name) {
            fetch('auth.php?action=track_guest', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ nickname: name })
            }).catch(() => {});
        }

        function syncNicknameForAccount(name) {
            fetch('auth.php?action=update_nickname', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ nickname: name })
            }).catch(() => {});
        }

        function syncNicknameToServer(name) {
            if (currentUserRole === 'guest') {
                trackGuestNickname(name);
            } else {
                syncNicknameForAccount(name);
            }
        }

        function confirmNickname() {
            const name = nicknameInput.value.trim();
            if (!name) {
                nicknameInput.focus();
                return;
            }
            saveNickname(name);
            syncNicknameToServer(name);
            updateWelcomeText();
            nicknameScreen.classList.add('hidden');
            startScreen.classList.remove('hidden');
            syncAccountBarVisibility();
        }

        function renameFromMenu() {
            if (menuOpen) toggleMenu();
            closeWeekPicker();
            closeHistoryResult();
            clearInterval(timer);
            clearInterval(essayTimer);
            quizScreen.classList.add('hidden');
            essayScreen.classList.add('hidden');
            resultScreen.classList.add('hidden');
            closeLobby();
            syncFinishButtonVisibility();
            showNicknameScreen();
        }

        (function initNickname() {
            if (getSavedNickname()) {
                nicknameScreen.classList.add('hidden');
                startScreen.classList.remove('hidden');
                updateWelcomeText();
            }
            syncAccountBarVisibility();
        })();

        /* Design theme: Kahoot is the default; each browser keeps its own choice. */
        const DESIGN_THEME_KEY = 'designTheme';
        const DESIGN_THEMES = ['light', 'dark', 'ocean', 'sunset', 'forest', 'royal', 'mono', 'kahoot'];

        function getSavedDesignTheme() {
            try {
                const saved = localStorage.getItem(DESIGN_THEME_KEY);
                return DESIGN_THEMES.includes(saved) ? saved : 'kahoot';
            } catch (e) {
                return 'kahoot';
            }
        }

        function applyTheme(theme) {
            if (!DESIGN_THEMES.includes(theme)) theme = 'kahoot';
            if (theme === 'light') {
                document.documentElement.removeAttribute('data-theme');
            } else {
                document.documentElement.setAttribute('data-theme', theme);
            }
            document.querySelectorAll('.theme-swatch, .design-option').forEach(btn => {
                btn.classList.toggle('active', btn.id === `theme-swatch-${theme}`);
            });
        }

        function setDesignTheme(theme) {
            applyTheme(theme);
            try { localStorage.setItem(DESIGN_THEME_KEY, theme); } catch (e) {}
        }

        function openMusicModal() {
            if (menuOpen) toggleMenu();
            document.getElementById('music-overlay').classList.remove('hidden');
        }

        function closeMusicModal() {
            document.getElementById('music-overlay').classList.add('hidden');
        }

        function openDesignModal() {
            if (menuOpen) toggleMenu();
            designOverlay.classList.remove('hidden');
        }

        function closeDesignModal() {
            designOverlay.classList.add('hidden');
        }

        applyTheme(getSavedDesignTheme());

        function toggleMenu() {
            menuOpen = !menuOpen;
            menuPanel.classList.toggle('hidden', !menuOpen);
            menuBtn.classList.toggle('active', menuOpen);
        }

        function goHome() {
            if (menuOpen) toggleMenu();
            closeWeekPicker();
            closeHistoryResult();
            clearInterval(timer);
            clearInterval(essayTimer);
            stopTestGuardSession();
            nicknameScreen.classList.add('hidden');
            quizScreen.classList.add('hidden');
            essayScreen.classList.add('hidden');
            resultScreen.classList.add('hidden');
            closeLobby();
            adminPanelPage.classList.add('hidden');
            usersPage.classList.add('hidden');
            historyPage.classList.add('hidden');
            startScreen.classList.remove('hidden');
            syncAccountBarVisibility();
            syncFinishButtonVisibility();
        }

        function restartFromMenu() {
            if (menuOpen) toggleMenu();
            closeWeekPicker();
            closeHistoryResult();
            clearInterval(timer);
            clearInterval(essayTimer);
            quizScreen.classList.add('hidden');
            essayScreen.classList.add('hidden');
            resultScreen.classList.add('hidden');
            closeLobby();
            startScreen.classList.remove('hidden');
            syncAccountBarVisibility();
            syncFinishButtonVisibility();
        }

        /* ---------- Unfinished test: saved in this browser so it can be continued ---------- */
        const RUN_KEY = 'testcomUnfinishedRun';
        let runActive = false;
        let runPhase = 'quiz';
        let pendingUnfinishedDelete = false;

        function readSavedRun() {
            try {
                const raw = localStorage.getItem(RUN_KEY);
                if (!raw) return null;
                const data = JSON.parse(raw);
                return data && data.v === 1 && Array.isArray(data.questions) ? data : null;
            } catch (e) { return null; }
        }

        function saveRun() {
            if (!runActive) return;
            try {
                localStorage.setItem(RUN_KEY, JSON.stringify({
                    v: 1,
                    mode: runMode,
                    week: runWeek,
                    hasEssay: runHasEssay,
                    essayTopic: runEssayTopic,
                    phase: runPhase,
                    questions: quizDatabase,
                    index: currentQuestionIndex,
                    score,
                    states: questionRenderState,
                    essayText: essayInput.value,
                    essayTimeLeft,
                    savedAt: Date.now()
                }));
            } catch (e) {}
        }

        function clearRun() {
            runActive = false;
            try { localStorage.removeItem(RUN_KEY); } catch (e) {}
            refreshResumeNote();
        }

        function describeSavedRun(saved) {
            const week = String(saved.week || '').padStart(2, '0');
            const name = saved.mode === 'week' ? 'Weekly Test - Week ' + week
                : saved.mode === 'essay' ? 'Essay - Week ' + week : 'Quiz';
            if (saved.phase === 'essay') {
                return name + ' - essay, ' + countWords(saved.essayText || '') + ' words written';
            }
            const done = (saved.states || []).filter(st => st && st.answered).length;
            return name + ' - ' + done + ' / ' + saved.questions.length + ' questions answered';
        }

        function refreshResumeNote() {
            const note = document.getElementById('resume-note');
            const saved = readSavedRun();
            note.classList.toggle('hidden', !saved);
            if (saved) document.getElementById('resume-note-detail').textContent = describeSavedRun(saved);
        }

        function resumeUnfinished() {
            const saved = readSavedRun();
            if (!saved) { refreshResumeNote(); return; }
            if (menuOpen) toggleMenu();
            runMode = saved.mode;
            runWeek = saved.week;
            runHasEssay = !!saved.hasEssay;
            runEssayTopic = saved.essayTopic || '';
            quizDatabase = saved.questions;
            currentQuestionIndex = saved.index || 0;
            score = saved.score || 0;
            questionRenderState = saved.states || [];
            reviewIndex = null;
            userEssayText = '';
            quizEndStatus = 'completed';
            startScreen.classList.add('hidden');
            historyPage.classList.add('hidden');
            syncAccountBarVisibility();
            startTestGuardSession();
            runActive = true;
            if (saved.phase === 'essay') {
                startEssaySection({ essayText: saved.essayText, essayTimeLeft: saved.essayTimeLeft || 20 * 60 });
                return;
            }
            runPhase = 'quiz';
            quizScreen.classList.remove('hidden');
            syncFinishButtonVisibility();
            const state = questionRenderState[currentQuestionIndex];
            if (state && !state.answered) {
                renderCurrentLiveState();
                updateNavButtons();
                startTimer();
            } else {
                if (state && state.answered) currentQuestionIndex++;
                loadQuestion();
            }
        }

        function askDiscardUnfinished() {
            if (!readSavedRun()) { refreshResumeNote(); return; }
            pendingUnfinishedDelete = true;
            pendingHistoryDeleteIds = [];
            document.getElementById('history-delete-title').innerText = 'Delete the unfinished test?';
            document.getElementById('history-delete-subtext').innerText = 'Your progress will be lost.';
            document.getElementById('history-delete-overlay').classList.remove('hidden');
        }

        function renderUnfinishedCard() {
            const old = historyList.querySelector('.history-pinned');
            if (old) old.remove();
            const saved = readSavedRun();
            if (!saved) return;
            const empty = historyList.querySelector('.history-empty');
            if (empty && historyList.children.length === 1) empty.remove();
            const card = document.createElement('div');
            card.className = 'history-row history-card history-pinned';
            card.tabIndex = 0;
            card.setAttribute('role', 'button');
            card.innerHTML = `
                <div class="history-card-body">
                    <div class="history-card-head">
                        <strong>${escapeHtml(describeSavedRun(saved))}</strong>
                        <span class="history-badge">Unfinished</span>
                    </div>
                    <div class="history-card-meta">Started ${escapeHtml(formatSiteDate(saved.savedAt || Date.now()))}</div>
                    <button type="button" class="btn history-card-continue">Continue</button>
                </div>
                <button type="button" class="history-trash-btn" aria-label="Delete the unfinished test">${ICON_TRASH}</button>
            `;
            card.addEventListener('click', resumeUnfinished);
            card.addEventListener('keydown', e => { if (e.key === 'Enter') resumeUnfinished(); });
            card.querySelector('.history-trash-btn').addEventListener('click', e => {
                e.stopPropagation();
                askDiscardUnfinished();
            });
            historyList.prepend(card);
        }

        /* Save right before the page is hidden or closed */
        document.addEventListener('visibilitychange', () => { if (document.hidden) saveRun(); });
        window.addEventListener('pagehide', saveRun);
        essayInput.addEventListener('input', (() => {
            let timer = null;
            return () => { clearTimeout(timer); timer = setTimeout(saveRun, 800); };
        })());

        /* ---------- Home: mode carousel (Quiz / Essay / Weekly Test / Game) ---------- */
        const MODES = ['quiz', 'essay', 'week', 'game'];
        let modeIndex = 0;
        let modeRotateLocked = false;
        let suppressModeClick = false;
        const modeStage = document.getElementById('mode-stage');

        function layoutModeCards() {
            modeStage.querySelectorAll('.mode-card').forEach((card, i) => {
                const rel = (i - modeIndex + MODES.length) % MODES.length;
                card.classList.toggle('is-center', rel === 0);
                card.classList.toggle('is-right', rel === 1);
                card.classList.toggle('is-back', rel === 2);
                card.classList.toggle('is-left', rel === 3);
                card.setAttribute('aria-hidden', rel === 0 ? 'false' : 'true');
            });
        }

        const MODE_NAMES = ['QUIZ', 'ESSAY', 'WEEKLY TEST', 'GAME'];
        const modeName = document.getElementById('mode-name');

        /* Name under the picture: slides in from the side the new mode comes from */
        function setModeName(step) {
            modeName.textContent = MODE_NAMES[modeIndex];
            if (!step) return;
            modeName.style.setProperty('--dir', step > 0 ? 1 : -1);
            modeName.classList.remove('swap');
            void modeName.offsetWidth;
            modeName.classList.add('swap');
        }

        /* Side pictures rest at the page edges, so a new mode slides in from the edge to the centre */
        function updateModeMetrics(stage) {
            stage = stage || modeStage;
            const stageWidth = stage.clientWidth;
            const card = stage.querySelector('.mode-card.is-center');
            if (!stageWidth || !card) return;
            const previewWidth = card.offsetWidth * 0.58;
            stage.style.setProperty('--preview-shift', Math.round(stageWidth / 2 - previewWidth * 0.25) + 'px');
        }
        window.addEventListener('resize', () => {
            updateModeMetrics(modeStage);
            if (typeof lobbyStage !== 'undefined') updateModeMetrics(lobbyStage);
        });

        function rotateMode(step) {
            if (modeRotateLocked) return;
            modeRotateLocked = true;
            modeIndex = (modeIndex + step + MODES.length) % MODES.length;
            modeStage.classList.add('rotating');
            layoutModeCards();
            setModeName(step);
            setTimeout(() => {
                modeRotateLocked = false;
                modeStage.classList.remove('rotating');
            }, 380);
        }

        function startSelectedMode() {
            const mode = MODES[modeIndex];
            if (mode === 'quiz') startQuiz();
            else if (mode === 'game') openLobby();
            else openWeekPicker(mode);
        }

        (function initModeCarousel() {
            let startX = null;
            modeStage.addEventListener('pointerdown', e => { startX = e.clientX; });
            modeStage.addEventListener('pointerup', e => {
                if (startX === null) return;
                const dx = e.clientX - startX;
                startX = null;
                if (Math.abs(dx) > 45) {
                    suppressModeClick = true;
                    setTimeout(() => { suppressModeClick = false; }, 0);
                    rotateMode(dx < 0 ? 1 : -1);
                }
            });
            modeStage.addEventListener('pointercancel', () => { startX = null; });
            modeStage.addEventListener('click', e => {
                if (suppressModeClick) return;
                const card = e.target.closest('.mode-card');
                if (!card) return;
                if (card.classList.contains('is-left')) rotateMode(-1);
                else if (card.classList.contains('is-right')) rotateMode(1);
            });
            document.addEventListener('keydown', e => {
                if (startScreen.classList.contains('hidden')) return;
                if (isTypingField(e.target)) return;
                if (document.querySelector('.confirm-overlay:not(.hidden), .auth-overlay:not(.hidden)')) return;
                if (e.key === 'ArrowRight') rotateMode(1);
                else if (e.key === 'ArrowLeft') rotateMode(-1);
            });
            layoutModeCards();
            setModeName(0);
            updateModeMetrics();
        })();

        /* ---------- Week picker: vertical drum for Weekly Test / Essay ---------- */
        const WEEK_COUNT = 15;
        const WHEEL_ITEM_H = 52;
        const weekPickerOverlay = document.getElementById('week-picker-overlay');
        const weekWheelScroll = document.getElementById('week-wheel-scroll');
        const weekWheelCaption = document.getElementById('week-wheel-caption');
        const weekPickerTitle = document.getElementById('week-picker-title');
        let weekPickerMode = 'week';
        let wheelDragMoved = false;

        function openWeekPicker(mode) {
            weekPickerMode = mode;
            weekPickerTitle.textContent = mode === 'essay' ? 'Essay' : 'Weekly Test';
            weekWheelScroll.innerHTML = '';
            for (let w = 1; w <= WEEK_COUNT; w++) {
                const item = document.createElement('div');
                item.className = 'wheel-item';
                item.setAttribute('role', 'option');
                item.textContent = 'WEEK ' + String(w).padStart(2, '0') + (mode === 'essay' ? ' ESSAY' : '');
                item.addEventListener('click', () => { if (!wheelDragMoved) scrollWheelTo(w - 1, true); });
                weekWheelScroll.appendChild(item);
            }
            weekPickerOverlay.classList.remove('hidden');
            weekWheelScroll.scrollTop = 0;
            updateWheel();
            weekWheelScroll.focus({ preventScroll: true });
        }

        function closeWeekPicker() {
            weekPickerOverlay.classList.add('hidden');
        }

        function wheelIndexNow() {
            return Math.min(WEEK_COUNT - 1, Math.max(0, Math.round(weekWheelScroll.scrollTop / WHEEL_ITEM_H)));
        }

        function scrollWheelTo(index, smooth) {
            weekWheelScroll.scrollTo({ top: index * WHEEL_ITEM_H, behavior: smooth ? 'smooth' : 'auto' });
            if (!smooth) updateWheel();
        }

        function updateWheel() {
            const position = weekWheelScroll.scrollTop / WHEEL_ITEM_H;
            const active = wheelIndexNow();
            weekWheelScroll.querySelectorAll('.wheel-item').forEach((item, i) => {
                const d = i - position;
                const dist = Math.abs(d);
                const tilt = Math.max(-70, Math.min(70, -d * 32));
                item.style.transform = `perspective(520px) rotateX(${tilt}deg) scale(${1 - Math.min(dist, 3) * 0.1})`;
                item.style.opacity = String(Math.max(0.18, 1 - dist * 0.3));
                item.style.filter = dist < 0.3 ? 'none' : `blur(${Math.min(dist, 3) * 1.5}px)`;
                item.classList.toggle('active', i === active);
                item.setAttribute('aria-selected', i === active ? 'true' : 'false');
            });
            const data = (typeof WEEKLY_TESTS !== 'undefined') ? WEEKLY_TESTS[active + 1] : null;
            weekWheelCaption.textContent = data
                ? (weekPickerMode === 'essay' ? 'One of ' + data.essays.length + ' possible topics is given at random when you start.' : data.title)
                : 'Weekly tests are not available right now.';
        }

        function confirmWeekPicker() {
            const week = wheelIndexNow() + 1;
            closeWeekPicker();
            if (weekPickerMode === 'essay') startEssayMode(week);
            else startWeeklyTest(week);
        }

        (function initWheel() {
            weekWheelScroll.addEventListener('scroll', () => requestAnimationFrame(updateWheel), { passive: true });
            weekWheelScroll.addEventListener('keydown', e => {
                if (e.key === 'ArrowDown') { e.preventDefault(); scrollWheelTo(Math.min(WEEK_COUNT - 1, wheelIndexNow() + 1), true); }
                else if (e.key === 'ArrowUp') { e.preventDefault(); scrollWheelTo(Math.max(0, wheelIndexNow() - 1), true); }
                else if (e.key === 'Enter') { e.preventDefault(); confirmWeekPicker(); }
                else if (e.key === 'Escape') { closeWeekPicker(); }
            });
            /* Mouse drag (touch and trackpads already scroll natively) */
            let dragStartY = 0, dragStartTop = 0, dragging = false;
            weekWheelScroll.addEventListener('pointerdown', e => {
                if (e.pointerType !== 'mouse') return;
                dragging = true;
                wheelDragMoved = false;
                dragStartY = e.clientY;
                dragStartTop = weekWheelScroll.scrollTop;
                weekWheelScroll.style.scrollSnapType = 'none';
                weekWheelScroll.setPointerCapture(e.pointerId);
            });
            weekWheelScroll.addEventListener('pointermove', e => {
                if (!dragging) return;
                const dy = e.clientY - dragStartY;
                if (Math.abs(dy) > 4) wheelDragMoved = true;
                weekWheelScroll.scrollTop = dragStartTop - dy;
            });
            const endDrag = () => {
                if (!dragging) return;
                dragging = false;
                weekWheelScroll.style.scrollSnapType = '';
                scrollWheelTo(wheelIndexNow(), true);
                setTimeout(() => { wheelDragMoved = false; }, 0);
            };
            weekWheelScroll.addEventListener('pointerup', endDrag);
            weekWheelScroll.addEventListener('pointercancel', endDrag);
        })();

        /* ---------- Game lobby (Battlegrounds style) ---------- */
        const lobbyPage = document.getElementById('lobby-page');
        const lobbyPlayersEl = document.getElementById('lobby-players');
        const lobbyPanel = document.getElementById('lobby-panel');
        const lobbyPlayBtn = document.getElementById('lobby-play');
        const lobbyTimerEl = document.getElementById('lobby-timer');
        const lobbyPlayLabel = document.getElementById('lobby-play-label');
        const lobbyTypePage = document.getElementById('lobby-type-page');
        const lobbyStage = document.getElementById('lobby-type-stage');
        const lobbyTypeTitle = document.getElementById('lobby-type-title');
        const lobbyOnlineList = document.getElementById('lobby-online-list');
        const lobbyOnlineCount = document.getElementById('lobby-online-count');
        const GAME_ORDER = ['answer-rush', 'timeline-rush', 'history-map', 'who-am-i', 'true-or-trap', 'history-duel', 'capture-the-answer', 'history-millionaire', 'mafia'];
        const LOBBY_TYPES = GAME_ORDER.map(id => Games.get(id)).filter(Boolean).map(g => ({ id: g.id, name: g.name.toUpperCase(), img: g.cover }));
        let lobbyBotTimers = [];
        const ICON_PERSON = '<svg viewBox="0 0 64 64" aria-hidden="true"><circle cx="32" cy="20" r="11"></circle><path d="M8 58c0-13 10-22 24-22s24 9 24 22z"></path></svg>';
        let lobbyType = 0;          // chosen game type
        let lobbyTypeView = 0;      // type currently shown in the picker
        let lobbyTypeLocked = false;
        let lobbySize = 1;          // 1 = solo, 2 = duo, 4 = squad
        let lobbySearching = false;
        let lobbyStartedAt = 0;
        let lobbyTimerId = null;
        let lobbyOnlineTimerId = null;
        let lobbyPlayers = [];      // slot 0 is you; null = still empty

        function lobbyMyName() {
            return getSavedNickname() || 'Guest';
        }

        function formatLobbyTime(ms) {
            const total = Math.floor(ms / 1000);
            return String(Math.floor(total / 60)).padStart(2, '0') + ':' + String(total % 60).padStart(2, '0');
        }

        function resetLobbyPlayers() {
            lobbyPlayers = Array.from({ length: lobbySize }, (_, i) => (i === 0 ? { nick: lobbyMyName() } : null));
        }

        function renderLobbySlots(justFoundIndex) {
            lobbyPlayersEl.innerHTML = '';
            lobbyPlayers.forEach((player, i) => {
                const slot = document.createElement('div');
                slot.className = 'lobby-slot' + (player ? ' lit' : '') + (!player && lobbySearching ? ' searching' : '') + (i === justFoundIndex ? ' found' : '');
                slot.innerHTML = `<div class="lobby-avatar">${ICON_PERSON}</div><div class="lobby-nick"></div>`;
                slot.querySelector('.lobby-nick').textContent = player ? player.nick : (lobbySearching ? 'SEARCHING...' : '');
                if (player && player.bot) slot.querySelector('.lobby-nick').insertAdjacentHTML('beforeend', ' <em>AI</em>');
                lobbyPlayersEl.appendChild(slot);
            });
        }

        /* ---------- Online players, invitations and the AI switch ---------- */
        const LOBBY_AI_KEY = 'testcomLobbyAI';
        let lobbyAI = true;
        try { lobbyAI = localStorage.getItem(LOBBY_AI_KEY) !== '0'; } catch (e) {}
        let lobbyOnlineRoom = false;      // my search / team lives on the server
        let lobbyStartSent = false;
        let lobbyLaunchTimer = null;
        const lobbyInvited = {};          // pub -> time I invited them
        const lobbyDismissed = {};        // invite id -> true
        let lobbyIgnoreRoomUntil = 0;     // a sync that was already in flight may still carry the room I just left

        function lobbyToast(text) {
            const box = document.getElementById('lobby-invites');
            const node = document.createElement('div');
            node.className = 'lobby-toast';
            node.textContent = text;
            box.appendChild(node);
            setTimeout(() => node.remove(), 3800);
        }

        function updateLobbyAIButton() {
            const btn = document.getElementById('lobby-ai');
            btn.classList.toggle('on', lobbyAI || lobbySize === 1);
            btn.classList.toggle('locked', lobbySize === 1);
            btn.setAttribute('aria-pressed', String(lobbyAI));
            btn.title = lobbySize === 1 ? 'Solo is always played with AI' : (lobbyAI ? 'AI players: ON' : 'AI players: OFF (real players only)');
        }

        function toggleLobbyAI() {
            if (lobbySearching) return;
            if (lobbySize === 1) { lobbyToast('Solo is played with AI. Choose Duo or Squad to play with real players.'); return; }
            lobbyAI = !lobbyAI;
            try { localStorage.setItem(LOBBY_AI_KEY, lobbyAI ? '1' : '0'); } catch (e) {}
            updateLobbyAIButton();
        }

        function renderLobbyOnline() {
            const users = Net.online || [];
            lobbyOnlineCount.textContent = String(users.length);
            if (Net.available === false) { lobbyOnlineList.innerHTML = '<div class="lobby-online-empty">Online play is unavailable right now</div>'; return; }
            if (!users.length) { lobbyOnlineList.innerHTML = '<div class="lobby-online-empty">No other players online</div>'; return; }
            lobbyOnlineList.innerHTML = '';
            users.forEach(user => {
                const row = document.createElement('div');
                row.className = 'lobby-online-user';
                const mate = !!(Net.room && Net.room.members.some(m => m.pub === user.pub));
                const invited = mate || (lobbyInvited[user.pub] && Date.now() - lobbyInvited[user.pub] < 60000);
                row.innerHTML = '<span></span><b></b><button type="button" class="lobby-invite-btn"></button>';
                row.querySelector('b').textContent = String(user.name).slice(0, 30);
                const btn = row.querySelector('button');
                btn.dataset.pub = user.pub;
                btn.textContent = invited ? '✓' : '+';
                btn.classList.toggle('sent', !!invited);
                const solo = lobbySize === 1;
                btn.classList.toggle('off', solo || user.state === 'busy');
                btn.title = solo ? 'Choose Duo or Squad to invite players' : (user.state === 'busy' ? 'Playing right now' : mate ? 'In your team' : invited ? 'Invite sent' : 'Invite to your team');
                btn.setAttribute('aria-label', 'Invite ' + user.name);
                lobbyOnlineList.appendChild(row);
            });
        }

        async function invitePlayer(pub) {
            if (lobbySize === 1) { lobbyToast('Choose Duo or Squad to invite players.'); return; }
            if (lobbySearching && !lobbyOnlineRoom) { lobbyToast('Cancel the AI search first.'); return; }
            if (Net.available === false) { lobbyToast('Online play is unavailable right now.'); return; }
            const res = await Net.call('invite', { game: LOBBY_TYPES[lobbyType].id, size: lobbySize, ai: lobbyAI ? 1 : 0, to: pub });
            if (res && res.ok) {
                lobbyInvited[pub] = Date.now();
                Net.room = res.room;
                lobbyToast('Invite sent.');
                applyLobbyRoom();
            } else {
                const text = { solo: 'Choose Duo or Squad to invite players.', offline: 'That player just went offline.', busy: 'That player is in a match.', full: 'Your team is full.', not_host: 'Only the team host can invite.' };
                lobbyToast(text[res && res.error] || 'Could not send the invite.');
            }
        }

        function renderLobbyInvites() {
            const box = document.getElementById('lobby-invites');
            box.querySelectorAll('.lobby-invite').forEach(n => n.remove());
            (Net.invites || []).filter(i => !lobbyDismissed[i.id]).forEach(inv => {
                const type = LOBBY_TYPES.find(t => t.id === inv.game);
                const card = document.createElement('div');
                card.className = 'lobby-invite';
                card.innerHTML = '<div><b></b><small></small></div><button type="button" class="join">JOIN</button><button type="button" class="no" aria-label="Decline">✕</button>';
                card.querySelector('b').textContent = inv.from_name;
                card.querySelector('small').textContent = (type ? type.name : inv.game) + ' · ' + (Number(inv.size) === 4 ? 'SQUAD' : 'DUO');
                card.querySelector('.join').onclick = async () => {
                    lobbyDismissed[inv.id] = true;
                    if (lobbySearching && !lobbyOnlineRoom) cancelLobbySearch();
                    const res = await Net.call('answer_invite', { id: inv.id, accept: true });
                    if (res && res.ok && res.room) { lobbyIgnoreRoomUntil = 0; Net.room = res.room; applyLobbyRoom(); } else lobbyToast('That team is no longer available.');
                    renderLobbyInvites();
                };
                card.querySelector('.no').onclick = () => { lobbyDismissed[inv.id] = true; Net.call('answer_invite', { id: inv.id, accept: false }); renderLobbyInvites(); };
                box.appendChild(card);
            });
        }

        function startLobbyOnlineUpdates() { Net.watch(true); }

        /* The server room is the source of truth while a team or an online search exists */
        function applyLobbyRoom() {
            if (lobbyPage.classList.contains('hidden') || Games.isRunning()) return;
            renderLobbyOnline();
            renderLobbyInvites();
            if (lobbySearching && !lobbyOnlineRoom) return;   // local AI search
            if (Date.now() < lobbyIgnoreRoomUntil) return;
            const room = Net.room;
            if (!room) {
                if (lobbyOnlineRoom) {
                    lobbyOnlineRoom = false;
                    if (lobbySearching) { lobbyToast('The team was closed.'); cancelLobbySearch(); }
                    else { resetLobbyPlayers(); renderLobbySlots(); lobbyPlayBtn.disabled = false; }
                }
                return;
            }
            lobbyOnlineRoom = true;
            const me = room.members.find(m => m.me);
            const iAmHost = !!(me && me.host);
            if (!iAmHost) {                       // guests follow the host's choices
                const index = LOBBY_TYPES.findIndex(t => t.id === room.game);
                if (index >= 0 && index !== lobbyType) { lobbyType = index; document.getElementById('lobby-type-name').textContent = LOBBY_TYPES[index].name; document.getElementById('lobby-type-img').src = LOBBY_TYPES[index].img; }
                if (room.size !== lobbySize) { lobbySize = room.size; document.querySelectorAll('.lobby-mode').forEach(b => b.classList.toggle('active', Number(b.dataset.size) === lobbySize)); }
                lobbyAI = !!room.ai;
                updateLobbyAIButton();
            }
            lobbyPlayers = Array.from({ length: room.size }, (_, i) => {
                const m = room.members.find(x => x.slot === i);
                return m ? { nick: m.me ? lobbyMyName() : m.name, bot: false } : null;
            });
            renderLobbySlots();
            lobbyPlayBtn.disabled = !iAmHost && room.status === 'lobby';
            if (room.status === 'search' && !lobbySearching) enterSearchingUI();
            if (room.status === 'lobby' && lobbySearching && !lobbyLaunchTimer) { lobbySearching = false; lobbyPlayBtn.classList.remove('searching', 'starting'); lobbyPanel.classList.remove('busy'); clearInterval(lobbyTimerId); renderLobbySlots(); }
            if (room.status === 'play') { scheduleOnlineLaunch(room); return; }
            if (room.status === 'search' && iAmHost && room.ai && room.members.length < room.size && !lobbyStartSent && Date.now() - lobbyStartedAt > 4000) {
                lobbyStartSent = true;
                Net.call('start').then(res => { if (res && res.ok) { Net.room = res.room; applyLobbyRoom(); } });
            }
        }

        function scheduleOnlineLaunch(room) {
            if (lobbyLaunchTimer) return;
            if (!lobbySearching) enterSearchingUI();
            clearInterval(lobbyTimerId);
            lobbyPlayLabel.textContent = room.members.length >= room.size ? 'TEAM READY' : 'MATCH FOUND';
            const wait = Math.max(0, (room.start_at - Net.serverNow()) * 1000);
            lobbyLaunchTimer = setTimeout(() => launchOnlineGame(room), wait);
        }

        function launchOnlineGame(room) {
            lobbyLaunchTimer = null;
            const me = room.members.find(m => m.me);
            if (!me) { cancelLobbySearch(); return; }
            const aiNames = Games.botNames(room.seed);
            const players = Array.from({ length: room.size }, (_, i) => {
                const m = room.members.find(x => x.slot === i);
                if (m) return { name: m.me ? lobbyMyName() : m.name, isYou: m.me, remote: !m.me, slot: i };
                return { name: aiNames[i], bot: { acc: 0.55 + Math.random() * 0.33, spd: 2.6 + Math.random() * 3.4 }, slot: i };
            });
            lobbyOnlineRoom = false;   // the game owns the room now
            Games.launch(room.game, { size: room.size, players, online: { seed: room.seed, roomId: room.id } });
        }

        async function startOnlineSearch() {
            if (Net.available === false) { lobbyToast('Online play is unavailable right now. Turn AI on to play with AI players.'); return; }
            enterSearchingUI();
            lobbyOnlineRoom = true;
            lobbyStartSent = false;
            renderLobbySlots();
            const res = await Net.call('queue', { game: LOBBY_TYPES[lobbyType].id, size: lobbySize, ai: lobbyAI ? 1 : 0 });
            if (!res || !res.ok) {
                const text = { not_host: 'Only the team host can start the search.', in_game: 'You are already in a match.', too_many: 'Your team is bigger than this mode.' };
                lobbyToast(text[res && res.error] || 'Online play is unavailable right now.');
                lobbyOnlineRoom = false;
                cancelLobbySearch();
                return;
            }
            Net.room = res.room;
            applyLobbyRoom();
        }

        function openLobby() {
            if (menuOpen) toggleMenu();
            closeWeekPicker();
            closeHistoryResult();
            clearInterval(timer);
            clearInterval(essayTimer);
            stopTestGuardSession();
            nicknameScreen.classList.add('hidden');
            startScreen.classList.add('hidden');
            quizScreen.classList.add('hidden');
            essayScreen.classList.add('hidden');
            resultScreen.classList.add('hidden');
            lobbyPage.classList.remove('hidden');
            syncAccountBarVisibility();
            syncFinishButtonVisibility();
            cancelLobbySearch();
            startLobbyOnlineUpdates();
        }

        function closeLobby() {
            if (typeof Games !== 'undefined') Games.stop();
            Net.watch(false);
            cancelLobbySearch();
            closeLobbyTypes();
            lobbyPage.classList.add('hidden');
        }

        function setLobbyMode(size) {
            if (lobbySearching) return;
            if (lobbyOnlineRoom && Net.room && !Net.room.members.some(m => m.me && m.host)) { lobbyToast('Only the team host can change the mode.'); return; }
            if (size === 1 && lobbyOnlineRoom) cancelLobbySearch();
            lobbySize = size;
            document.querySelectorAll('.lobby-mode').forEach(btn => {
                btn.classList.toggle('active', Number(btn.dataset.size) === size);
            });
            resetLobbyPlayers();
            renderLobbySlots();
            updateLobbyAIButton();
            renderLobbyOnline();
        }

        function lobbyPlayClick() {
            if (LOBBY_TYPES[lobbyType].id === 'mafia') { Mafia.open(); return; }   // Mafia has its own (Russian) room lobby
            if (lobbySearching) cancelLobbySearch();
            else startLobbySearch();
        }

        function enterSearchingUI() {
            lobbySearching = true;
            lobbyStartedAt = Date.now();
            lobbyTimerEl.textContent = '00:00';
            lobbyPlayLabel.textContent = 'FINDING PLAYERS';
            lobbyPlayBtn.classList.remove('starting');
            void lobbyPlayBtn.offsetWidth;
            lobbyPlayBtn.classList.add('searching', 'starting');
            lobbyPanel.classList.add('busy');
            clearInterval(lobbyTimerId);
            lobbyTimerId = setInterval(() => {
                lobbyTimerEl.textContent = formatLobbyTime(Date.now() - lobbyStartedAt);
            }, 250);
        }

        function startLobbySearch() {
            /* AI on and no team: play locally with AI. AI off, or a team exists: the server room. */
            if (lobbySize > 1 && (!lobbyAI || Net.room)) { startOnlineSearch(); return; }
            enterSearchingUI();
            renderLobbySlots();
            /* No matchmaking server yet: AI players join after a few seconds (a real provider can call lobbyFoundPlayer() instead) */
            const names = Games.makePlayers(lobbySize).map(p => p.name);
            if (lobbySize === 1) {
                lobbyBotTimers.push(setTimeout(lobbyMatchReady, 1800));
            } else {
                for (let i = 1; i < lobbySize; i++) {
                    lobbyBotTimers.push(setTimeout(() => lobbyFoundPlayer(names[i], true), 1800 + (i - 1) * 1500 + Math.random() * 900));
                }
            }
        }

        function lobbyMatchReady() {
            clearInterval(lobbyTimerId);
            lobbyPlayLabel.textContent = lobbySize === 1 ? 'MATCH READY' : 'TEAM READY';
            lobbyBotTimers.push(setTimeout(launchLobbyGame, 1000));
        }

        function launchLobbyGame() {
            if (!lobbySearching) return;
            const names = lobbyPlayers.map(p => (p ? p.nick : null));
            const players = Games.makePlayers(lobbySize, names).map((p, i) => {
                if (i > 0 && lobbyPlayers[i] && lobbyPlayers[i].bot === false) delete p.bot;
                return p;
            });
            Games.launch(LOBBY_TYPES[lobbyType].id, { size: lobbySize, players });
        }

        function cancelLobbySearch() {
            const wasOnline = lobbyOnlineRoom;
            lobbySearching = false;
            clearInterval(lobbyTimerId);
            clearTimeout(lobbyLaunchTimer);
            lobbyLaunchTimer = null;
            lobbyStartSent = false;
            lobbyBotTimers.forEach(clearTimeout);
            lobbyBotTimers = [];
            if (wasOnline) {
                lobbyOnlineRoom = false;
                lobbyIgnoreRoomUntil = Date.now() + 1800;
                const room = Net.room;
                const host = room && room.members.some(m => m.me && m.host);
                if (host && room.members.length > 1 && room.status === 'search') Net.call('stop_search').then(() => Net.sync());
                else Net.call('leave').then(() => { Net.room = null; Net.sync(); });
            }
            lobbyPlayBtn.classList.remove('searching', 'starting');
            lobbyPanel.classList.remove('busy');
            resetLobbyPlayers();
            renderLobbySlots();
        }

        /* Called when another player is matched: lights the next empty slot and shows the nickname */
        function lobbyFoundPlayer(nick, isBot) {
            const index = lobbyPlayers.findIndex((p, i) => i > 0 && !p);
            if (index === -1) return;
            lobbyPlayers[index] = { nick: String(nick).slice(0, 24), bot: !!isBot };
            renderLobbySlots(index);
            if (lobbyPlayers.every(Boolean)) lobbyMatchReady();
        }

        /* --- Game-type picker --- */
        function layoutLobbyCards() {
            const cards = lobbyStage.querySelectorAll('.mode-card');
            const n = cards.length;
            cards.forEach((card, i) => {
                const rel = (i - lobbyTypeView + n) % n;
                card.classList.toggle('is-center', rel === 0);
                card.classList.toggle('is-right', rel === 1);
                card.classList.toggle('is-left', rel === n - 1);
                card.classList.toggle('is-back', rel !== 0 && rel !== 1 && rel !== n - 1);
            });
        }

        function setLobbyTypeTitle(step) {
            lobbyTypeTitle.textContent = LOBBY_TYPES[lobbyTypeView].name;
            if (!step) return;
            lobbyTypeTitle.style.setProperty('--dir', step > 0 ? 1 : -1);
            lobbyTypeTitle.classList.remove('swap');
            void lobbyTypeTitle.offsetWidth;
            lobbyTypeTitle.classList.add('swap');
        }

        function rotateLobbyType(step) {
            if (lobbyTypeLocked) return;
            lobbyTypeLocked = true;
            lobbyTypeView = (lobbyTypeView + step + LOBBY_TYPES.length) % LOBBY_TYPES.length;
            layoutLobbyCards();
            setLobbyTypeTitle(step);
            setTimeout(() => { lobbyTypeLocked = false; }, 380);
        }

        function openLobbyTypes() {
            if (lobbySearching) return;
            lobbyTypeView = lobbyType;
            layoutLobbyCards();
            setLobbyTypeTitle(0);
            lobbyTypePage.classList.remove('hidden');
            updateModeMetrics(lobbyStage);
            lobbyStage.classList.remove('lit');
            void lobbyStage.offsetWidth;
            setTimeout(() => lobbyStage.classList.add('lit'), 150);
        }

        function closeLobbyTypes() {
            lobbyTypePage.classList.add('hidden');
            lobbyStage.classList.remove('lit');
        }

        function selectLobbyType() {
            lobbyType = lobbyTypeView;
            document.getElementById('lobby-type-name').textContent = LOBBY_TYPES[lobbyType].name;
            document.getElementById('lobby-type-img').src = LOBBY_TYPES[lobbyType].img;
            document.getElementById('lobby-modes').classList.toggle('hidden', LOBBY_TYPES[lobbyType].id === 'mafia');
            closeLobbyTypes();
        }

        (function initLobby() {
            document.getElementById('lobby-cancel-icon').innerHTML = ICON_X;
            document.getElementById('lobby-prev-icon').innerHTML = ICON_CHEVRON_LEFT;
            document.getElementById('lobby-next-icon').innerHTML = ICON_CHEVRON_RIGHT;
            let startX = null;
            let swiped = false;
            lobbyStage.addEventListener('pointerdown', e => { startX = e.clientX; });
            lobbyStage.addEventListener('pointerup', e => {
                if (startX === null) return;
                const dx = e.clientX - startX;
                startX = null;
                if (Math.abs(dx) > 45) {
                    swiped = true;
                    setTimeout(() => { swiped = false; }, 0);
                    rotateLobbyType(dx < 0 ? 1 : -1);
                }
            });
            lobbyStage.addEventListener('click', e => {
                if (swiped) return;
                const card = e.target.closest('.mode-card');
                if (!card) return;
                if (card.classList.contains('is-left')) rotateLobbyType(-1);
                else if (card.classList.contains('is-right')) rotateLobbyType(1);
            });
            document.addEventListener('keydown', e => {
                if (lobbyTypePage.classList.contains('hidden')) return;
                if (e.key === 'ArrowRight') rotateLobbyType(1);
                else if (e.key === 'ArrowLeft') rotateLobbyType(-1);
                else if (e.key === 'Enter') selectLobbyType();
                else if (e.key === 'Escape') closeLobbyTypes();
            });
            LOBBY_TYPES.forEach(t => {
                const card = document.createElement('div');
                card.className = 'mode-card';
                card.innerHTML = `<img src="${t.img}" alt="${t.name}" draggable="false">`;
                lobbyStage.appendChild(card);
            });
            document.getElementById('lobby-type-name').textContent = LOBBY_TYPES[0].name;
            document.getElementById('lobby-type-img').src = LOBBY_TYPES[0].img;
            Games.config({
                nick: lobbyMyName,
                onChangeGame() { lobbyPage.classList.remove('hidden'); cancelLobbySearch(); openLobbyTypes(); },
                onHome() { goHome(); }
            });
            Net.on(applyLobbyRoom);
            lobbyOnlineList.addEventListener('click', e => {
                const btn = e.target.closest('.lobby-invite-btn');
                if (btn && !btn.classList.contains('sent')) invitePlayer(btn.dataset.pub);
            });
            updateLobbyAIButton();
            resetLobbyPlayers();
            renderLobbySlots();
        })();

        /* A Mafia invitation link (?mafia=CODE) opens the game lobby and joins that room */
        (function openMafiaFromLink() {
            const code = Mafia.joinCodeFromUrl();
            if (!code) return;
            setTimeout(() => {
                const index = LOBBY_TYPES.findIndex(t => t.id === 'mafia');
                if (index >= 0) {
                    lobbyType = index;
                    document.getElementById('lobby-type-name').textContent = LOBBY_TYPES[index].name;
                    document.getElementById('lobby-type-img').src = LOBBY_TYPES[index].img;
                    document.getElementById('lobby-modes').classList.add('hidden');
                }
                openLobby();
                Mafia.open(code);
            }, 700);
        })();

        function toggleMute() {
            bgMusic.muted = !bgMusic.muted;
            const iconHtml = bgMusic.muted ? ICON_SPEAKER_MUTED : ICON_SPEAKER;
            muteIcon.innerHTML = iconHtml;
            if (menuMuteIcon) menuMuteIcon.innerHTML = iconHtml;
        }

        /* Background music: single looping track, the site's own generated audio (Music/General.mp3) */
        const bgMusic = document.getElementById('bg-music');
        bgMusic.volume = 0.4;

        /* Sound track choice: keep the current generated track ("General") or
           switch to a second, more neutral generated track ("Standard"). */
        const SOUND_TRACKS = { general: 'Music/General.mp3', standard: 'Music/Standard.mp3' };

        function setSoundTrack(track) {
            const wasPlaying = !bgMusic.paused;
            bgMusic.src = SOUND_TRACKS[track] || SOUND_TRACKS.general;
            document.querySelectorAll('.sound-track-btn').forEach(btn => {
                btn.classList.toggle('active', btn.id === `sound-track-${track}`);
            });
            try { localStorage.setItem('soundTrack', track); } catch (e) {}
            if (wasPlaying) {
                bgMusic.play().catch(() => {});
            } else {
                bgMusic.play().catch(() => {});
            }
            closeMusicModal();
        }

        (function initSoundTrack() {
            let saved = 'general';
            try { saved = localStorage.getItem('soundTrack') || 'general'; } catch (e) {}
            bgMusic.src = SOUND_TRACKS[saved] || SOUND_TRACKS.general;
            document.querySelectorAll('.sound-track-btn').forEach(btn => {
                btn.classList.toggle('active', btn.id === `sound-track-${saved}`);
            });
        })();

        function tryPlayMusic() {
            const playPromise = bgMusic.play();
            if (playPromise && playPromise.catch) {
                playPromise.catch(() => {
                    const resume = () => { bgMusic.play().catch(() => {}); };
                    document.addEventListener('click', resume, { once: true });
                    document.addEventListener('keydown', resume, { once: true });
                    document.addEventListener('touchstart', resume, { once: true });
                });
            }
        }
        tryPlayMusic();

        function startTestGuardSession() {
            guardWarningCount = 0;
            lastGuardViolationAt = 0;
            pendingVisibilityWarning = false;
            closeGuardWarning();
            testGuardRunning = true;
        }

        function stopTestGuardSession() {
            testGuardRunning = false;
            pendingVisibilityWarning = false;
            closeGuardWarning();
        }

        function playGuardSignal() {
            try {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                if (!AudioContext) return;
                const ctx = new AudioContext();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.value = 880;
                gain.gain.setValueAtTime(0.001, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.18, ctx.currentTime + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.35);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start();
                osc.stop(ctx.currentTime + 0.38);
                setTimeout(() => ctx.close().catch(() => {}), 600);
            } catch (e) {}
        }

        function guardMessage(reason) {
            if (reason === 'tab') return 'You left the test page. Stay on this page while the test is running.';
            if (reason === 'zoom') return 'Zoom is blocked during the test.';
            if (reason === 'copy') return 'Copying is blocked during the test.';
            if (reason === 'tools') return 'Browser shortcuts are blocked during the test.';
            return 'This action is blocked during the test.';
        }

        function registerGuardViolation(reason) {
            if (!isTestGuardActive()) return;
            const now = Date.now();
            if (now - lastGuardViolationAt < 1500) return;
            lastGuardViolationAt = now;
            guardWarningCount++;
            playGuardSignal();
            guardWarningTitle.innerText = `Warning ${guardWarningCount} / ${MAX_GUARD_WARNINGS}`;
            guardWarningText.innerText = guardMessage(reason);
            guardWarning.classList.remove('hidden');
            if (guardWarningCount >= MAX_GUARD_WARNINGS) {
                setTimeout(disqualifyTest, 700);
            }
        }

        function closeGuardWarning() {
            guardWarning.classList.add('hidden');
        }

        function disqualifyTest() {
            if (!testGuardRunning) return;
            quizEndStatus = 'disqualified';
            clearInterval(timer);
            clearInterval(essayTimer);
            quizScreen.classList.add('hidden');
            essayScreen.classList.add('hidden');
            syncFinishButtonVisibility();
            userEssayText = userEssayText || essayInput.value.trim();
            endQuiz();
        }

        document.addEventListener('visibilitychange', () => {
            if (!isTestGuardActive()) return;
            if (document.hidden) {
                pendingVisibilityWarning = true;
            } else if (pendingVisibilityWarning) {
                pendingVisibilityWarning = false;
                registerGuardViolation('tab');
            }
        });

        window.addEventListener('blur', () => {
            if (isTestGuardActive()) pendingVisibilityWarning = true;
        });

        window.addEventListener('focus', () => {
            if (isTestGuardActive() && pendingVisibilityWarning) {
                pendingVisibilityWarning = false;
                registerGuardViolation('tab');
            }
        });

        window.addEventListener('beforeunload', e => {
            if (!isTestGuardActive()) return;
            e.preventDefault();
            e.returnValue = '';
        });

        /* What is currently being played: 'quiz' (90 questions, no essay), 'week' (one week's
           questions + that week's essay) or 'essay' (one week's essay only). */
        let runMode = 'quiz';
        let runWeek = null;
        let runHasEssay = false;
        let runEssayTopic = '';

        function pickWeekEssay(week) {
            const topics = WEEKLY_TESTS[week].essays;
            return topics[Math.floor(Math.random() * topics.length)];
        }

        function startQuiz() {
            runMode = 'quiz';
            runWeek = null;
            runHasEssay = false;
            runEssayTopic = '';
            beginQuestions(fullQuizPool);
        }

        function startWeeklyTest(week) {
            if (typeof WEEKLY_TESTS === 'undefined' || !WEEKLY_TESTS[week]) return;
            runMode = 'week';
            runWeek = week;
            runHasEssay = true;
            runEssayTopic = pickWeekEssay(week);
            beginQuestions(WEEKLY_TESTS[week].questions);
        }

        function startEssayMode(week) {
            if (typeof WEEKLY_TESTS === 'undefined' || !WEEKLY_TESTS[week]) return;
            runMode = 'essay';
            runWeek = week;
            runHasEssay = true;
            runEssayTopic = pickWeekEssay(week);
            if (menuOpen) toggleMenu();
            startScreen.classList.add('hidden');
            syncAccountBarVisibility();
            quizDatabase = [];
            currentQuestionIndex = 0;
            score = 0;
            questionRenderState = [];
            reviewIndex = null;
            userEssayText = '';
            quizEndStatus = 'completed';
            startTestGuardSession();
            syncFinishButtonVisibility();
            startEssaySection();
        }

        function beginQuestions(questions) {
            if (menuOpen) toggleMenu();
            startScreen.classList.add('hidden');
            syncAccountBarVisibility();
            quizScreen.classList.remove('hidden');
            quizDatabase = shuffleArray(questions);
            currentQuestionIndex = 0;
            score = 0;
            questionRenderState = [];
            reviewIndex = null;
            userEssayText = '';
            quizEndStatus = 'completed';
            startTestGuardSession();
            syncFinishButtonVisibility();
            runActive = true;
            runPhase = 'quiz';
            loadQuestion();
        }

        function loadQuestion() {
            clearInterval(timer);
            reviewIndex = null;
            if (currentQuestionIndex >= quizDatabase.length) {
                if (runHasEssay) {
                    startEssaySection();
                } else {
                    quizScreen.classList.add('hidden');
                    syncFinishButtonVisibility();
                    endQuiz();
                }
                return;
            }

            const currentQ = quizDatabase[currentQuestionIndex];
            let allAnswers = [currentQ.correctAnswer, ...currentQ.wrongAnswers];
            allAnswers.sort(() => Math.random() - 0.5);
            questionRenderState[currentQuestionIndex] = { allAnswers, selected: null, isCorrect: null, answered: false };
            saveRun();

            renderCurrentLiveState();
            updateNavButtons();
            startTimer();
        }

        function renderCurrentLiveState() {
            const currentQ = quizDatabase[currentQuestionIndex];
            const state = questionRenderState[currentQuestionIndex];
            questionCounter.innerText = `Question ${currentQuestionIndex + 1} / ${quizDatabase.length}`;
            scoreDisplay.innerText = `Score: ${score}`;
            questionText.innerText = currentQ.question;

            answersContainer.innerHTML = '';
            answersContainer.classList.remove('answered');
            state.allAnswers.forEach(answer => {
                const btn = document.createElement('button');
                btn.className = 'answer-btn';
                btn.innerText = answer;
                btn.onclick = () => checkAnswer(btn, answer, currentQ.correctAnswer);
                if (showAllAnswers && answer === currentQ.correctAnswer) {
                    btn.classList.add('answer-key-hint');
                    addResultBadge(btn, ICON_CHECK);
                }
                answersContainer.appendChild(btn);
            });
        }

        function renderReviewQuestion(idx) {
            const q = quizDatabase[idx];
            const state = questionRenderState[idx];
            questionCounter.innerText = `Question ${idx + 1} / ${quizDatabase.length} (Review)`;
            scoreDisplay.innerText = `Score: ${score}`;
            questionText.innerText = q.question;

            answersContainer.innerHTML = '';
            answersContainer.classList.add('answered');
            state.allAnswers.forEach(answer => {
                const btn = document.createElement('button');
                btn.className = 'answer-btn';
                btn.innerText = answer;
                btn.disabled = true;
                if (answer === q.correctAnswer) {
                    btn.classList.add('answer-correct');
                    addResultBadge(btn, ICON_CHECK);
                } else if (answer === state.selected) {
                    btn.classList.add('answer-wrong');
                    addResultBadge(btn, ICON_X);
                }
                answersContainer.appendChild(btn);
            });
        }

        function updateNavButtons() {
            const canGoBack = reviewIndex !== null ? reviewIndex > 0 : currentQuestionIndex > 0;
            const canGoForward = reviewIndex !== null;
            navBackBtn.disabled = !canGoBack;
            navForwardBtn.disabled = !canGoForward;
        }

        function navigateBack() {
            if (reviewIndex === null) {
                if (currentQuestionIndex === 0) return;
                clearInterval(timer);
                reviewIndex = currentQuestionIndex - 1;
            } else if (reviewIndex > 0) {
                reviewIndex--;
            } else {
                return;
            }
            renderReviewQuestion(reviewIndex);
            updateNavButtons();
        }

        function navigateForward() {
            if (reviewIndex === null) return;
            reviewIndex++;
            if (reviewIndex >= currentQuestionIndex) {
                reviewIndex = null;
                renderCurrentLiveState();
                resumeTimerInterval();
            } else {
                renderReviewQuestion(reviewIndex);
            }
            updateNavButtons();
        }

        function startTimer() {
            timeLeft = TIME_PER_QUESTION;
            timerProgress.style.width = '100%';
            resumeTimerInterval();
        }

        function resumeTimerInterval() {
            clearInterval(timer);
            const step = 100 / TIME_PER_QUESTION;
            timer = setInterval(() => {
                timeLeft--;
                timerProgress.style.width = `${Math.max(timeLeft, 0) * step}%`;

                if (timeLeft <= 0) {
                    clearInterval(timer);
                    handleTimeout();
                }
            }, 1000);
        }

        const CORRECT_EMOJIS = ['🎉', '🔥', '💯', '✨', '👏', '🚀', '⭐', '😎'];
        const ANSWER_REVEAL_DELAY = 1400;

        const answerToast = document.getElementById('answer-toast');
        const answerToastIcon = document.getElementById('answer-toast-icon');
        const answerToastText = document.getElementById('answer-toast-text');

        function showAnswerToast(isCorrect) {
            answerToast.classList.remove('correct', 'wrong');
            answerToast.classList.add(isCorrect ? 'correct' : 'wrong');
            answerToastIcon.innerHTML = isCorrect ? ICON_CHECK : ICON_X;
            answerToastText.innerText = isCorrect ? 'Correct' : 'Incorrect';
            answerToast.classList.remove('hidden');
            requestAnimationFrame(() => answerToast.classList.add('show'));
            clearTimeout(answerToast._hideTimer);
            answerToast._hideTimer = setTimeout(() => {
                answerToast.classList.remove('show');
            }, ANSWER_REVEAL_DELAY - 200);
        }

        function addResultBadge(btn, iconHtml) {
            const badge = document.createElement('span');
            badge.className = 'answer-result-icon icon-svg';
            badge.innerHTML = iconHtml;
            btn.appendChild(badge);
        }

        function lockAnswers() {
            answersContainer.classList.add('answered');
            Array.from(answersContainer.children).forEach(btn => { btn.disabled = true; });
        }

        function revealCorrectAnswer(correct) {
            const btn = Array.from(answersContainer.children).find(b => b.innerText === correct);
            if (btn) {
                btn.classList.add('answer-correct');
                addResultBadge(btn, ICON_CHECK);
            }
            return btn;
        }

        function spawnEmojiBurst(anchorEl) {
            const rect = anchorEl.getBoundingClientRect();
            const centerX = rect.left + rect.width / 2;
            const centerY = rect.top + rect.height / 2;
            const count = 7;
            for (let i = 0; i < count; i++) {
                const emoji = CORRECT_EMOJIS[Math.floor(Math.random() * CORRECT_EMOJIS.length)];
                const el = document.createElement('div');
                el.className = 'emoji-burst';
                el.innerText = emoji;
                const angle = (Math.PI * 2 * i) / count + (Math.random() * 0.5 - 0.25);
                const distance = 70 + Math.random() * 50;
                el.style.left = `${centerX}px`;
                el.style.top = `${centerY}px`;
                el.style.setProperty('--dx', `${Math.cos(angle) * distance}px`);
                el.style.setProperty('--dy', `${Math.sin(angle) * distance}px`);
                el.style.animationDelay = `${i * 35}ms`;
                document.body.appendChild(el);
                el.addEventListener('animationend', () => el.remove());
            }
        }

        function handleTimeout() {
            lockAnswers();
            const currentQ = quizDatabase[currentQuestionIndex];
            const state = questionRenderState[currentQuestionIndex];
            state.selected = null;
            state.isCorrect = false;
            state.answered = true;
            saveRun();
            revealCorrectAnswer(currentQ.correctAnswer);
            showAnswerToast(false);
            setTimeout(() => {
                currentQuestionIndex++;
                loadQuestion();
            }, ANSWER_REVEAL_DELAY);
        }

        function checkAnswer(clickedBtn, selected, correct) {
            clearInterval(timer);
            lockAnswers();

            const isCorrect = selected === correct;
            const state = questionRenderState[currentQuestionIndex];
            state.selected = selected;
            state.isCorrect = isCorrect;
            state.answered = true;

            if (isCorrect) {
                score += 100;
                clickedBtn.classList.add('answer-correct');
                addResultBadge(clickedBtn, ICON_CHECK);
                spawnEmojiBurst(clickedBtn);
                showAnswerToast(true);
            } else {
                clickedBtn.classList.add('answer-wrong');
                addResultBadge(clickedBtn, ICON_X);
                revealCorrectAnswer(correct);
                showAnswerToast(false);
            }
            scoreDisplay.innerText = `Score: ${score}`;
            saveRun();

            setTimeout(() => {
                currentQuestionIndex++;
                loadQuestion();
            }, ANSWER_REVEAL_DELAY);
        }

        function startEssaySection(resume) {
            clearInterval(essayTimer);
            quizScreen.classList.add('hidden');
            syncFinishButtonVisibility();
            essayScreen.classList.remove('hidden');
            showEssayTopic(runEssayTopic);
            const weekLabel = 'Week ' + String(runWeek).padStart(2, '0');
            document.getElementById('essay-title-text').textContent =
                runMode === 'essay' ? weekLabel + ' Essay' : weekLabel + ': Final Essay';
            essayTimeLeft = resume ? resume.essayTimeLeft : 20 * 60;
            essaySubmitted = false;
            essayInput.value = resume ? (resume.essayText || '') : '';
            updateWordCounter();
            runActive = true;
            runPhase = 'essay';
            saveRun();

            essayTimer = setInterval(() => {
                essayTimeLeft--;
                if (essayTimeLeft % 5 === 0) saveRun();
                let minutes = Math.floor(essayTimeLeft / 60);
                let seconds = essayTimeLeft % 60;
                essayTimerDisplay.innerText = `Time left: ${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;

                if (essayTimeLeft <= 0) {
                    clearInterval(essayTimer);
                    submitEssay();
                }
            }, 1000);
        }

        function submitEssay() {
            if (essaySubmitted) return;
            clearInterval(essayTimer);
            essaySubmitted = true;
            userEssayText = essayInput.value.trim();
            endQuiz();
        }

        function downloadEssay() {
            downloadEssayText(userEssayText);
        }

        function downloadEssayText(text) {
            if (!text || text.length === 0) return;
            const blob = new Blob([text], { type: 'text/plain;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'essay.txt';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }

        function correctQuestionCount() {
            return questionRenderState.filter(state => state && state.answered && state.isCorrect).length;
        }

        function animateCorrectCount(target, el) {
            const correctCountNumber = el || document.getElementById('correct-count-number');
            if (!correctCountNumber) return;
            const duration = 1100;
            const startTime = performance.now();
            correctCountNumber.innerText = '0';
            correctCountNumber.classList.remove('count-spin');
            void correctCountNumber.offsetWidth;
            correctCountNumber.classList.add('count-spin');

            function tick(now) {
                const progress = Math.min((now - startTime) / duration, 1);
                const eased = 1 - Math.pow(1 - progress, 3);
                correctCountNumber.innerText = String(Math.round(target * eased));
                if (progress < 1) {
                    requestAnimationFrame(tick);
                } else {
                    correctCountNumber.innerText = String(target);
                }
            }
            requestAnimationFrame(tick);
        }

        function fillUserInfo(nameEl, emailEl) {
            nameEl.textContent = getSavedNickname() || 'Guest';
            const email = (currentUserEmail && currentUserRole !== 'guest') ? currentUserEmail : '';
            emailEl.textContent = email;
            emailEl.classList.toggle('hidden', !email);
        }

        function endQuiz() {
            clearRun();
            fillUserInfo(document.getElementById('result-user-name'), document.getElementById('result-user-email'));
            stopTestGuardSession();
            essayScreen.classList.add('hidden');
            resultScreen.classList.remove('hidden');
            const showQuizResult = runMode !== 'essay';
            const showEssayResult = runMode !== 'quiz' || quizEndStatus === 'disqualified';
            document.querySelector('.correct-count-wrap').classList.toggle('hidden', !showQuizResult);
            finalScore.classList.toggle('hidden', !showQuizResult);
            essayStatus.classList.toggle('hidden', !showEssayResult);
            if (showQuizResult) {
                finalScore.innerText = `Quiz Score: ${score} / ${quizDatabase.length * 100}`;
                animateCorrectCount(correctQuestionCount());
            }

            if (quizEndStatus === 'disqualified') {
                essayStatus.innerText = 'Test stopped: 3 warnings were reached.';
                downloadEssayBtn.classList.add('hidden');
            } else if (userEssayText.length > 0) {
                essayStatus.innerText = `Essay Status: Submitted (${countWords(userEssayText)} words).`;
                downloadEssayBtn.classList.remove('hidden');
            } else {
                essayStatus.innerText = `Essay Status: Not submitted / Empty.`;
                downloadEssayBtn.classList.add('hidden');
            }
            recordTestHistory();
        }

        function restartQuiz() {
            quizEndStatus = 'completed';
            resultScreen.classList.add('hidden');
            startScreen.classList.remove('hidden');
            syncAccountBarVisibility();
            syncFinishButtonVisibility();
        }

        /* Finish-test button + site-styled confirmation (quiz screen only) */
        function syncFinishButtonVisibility() {
            finishTestBtn.classList.toggle('hidden', quizScreen.classList.contains('hidden'));
        }

        function openFinishConfirm() {
            const remaining = Math.max(quizDatabase.length - currentQuestionIndex - 1, 0);
            finishConfirmSubtext.innerText = runHasEssay
                ? `You still have ${remaining} question(s) and the essay task remaining.`
                : `You still have ${remaining} question(s) remaining.`;
            finishConfirmOverlay.classList.remove('hidden');
        }

        function closeFinishConfirm() {
            finishConfirmOverlay.classList.add('hidden');
        }

        function confirmFinishTest() {
            closeFinishConfirm();
            quizEndStatus = 'finished_early';
            clearInterval(timer);
            quizScreen.classList.add('hidden');
            syncFinishButtonVisibility();
            userEssayText = '';
            endQuiz();
        }

        /* Full test review: every answered question + the essay, each in its own block */
        function escapeHtml(str) {
            const div = document.createElement('div');
            div.innerText = str;
            return div.innerHTML;
        }

        function formatSiteDate(value, databaseTime = false) {
            const raw = String(value ?? '').trim();
            const normalized = databaseTime && /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(raw)
                ? raw.replace(' ', 'T') + 'Z'
                : value;
            const date = new Date(normalized);
            if (Number.isNaN(date.getTime())) return raw.replace(/:(\d{2})(?=\s|$)/, '');
            return new Intl.DateTimeFormat(undefined, {
                timeZone: 'Asia/Almaty',
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            }).format(date);
        }

        function buildCurrentReviewSnapshot() {
            return {
                mode: runMode,
                week: runWeek,
                essayTopic: runHasEssay ? (currentEssayTopic || '(no topic)') : '',
                essayText: userEssayText || '',
                questions: questionRenderState.map((state, idx) => {
                    if (!state || !state.answered) return null;
                    const q = quizDatabase[idx];
                    return {
                        number: idx + 1,
                        question: q.question,
                        selected: state.selected || 'No answer (time ran out)',
                        correctAnswer: q.correctAnswer,
                        isCorrect: !!state.isCorrect
                    };
                }).filter(Boolean)
            };
        }

        function renderReviewSnapshot(snapshot) {
            resultsReviewList.innerHTML = '';
            const questions = Array.isArray(snapshot.questions) ? snapshot.questions : [];

            questions.forEach((item, idx) => {
                const questionNumber = Number(item.number) || (idx + 1);
                const yourAnswerText = item.selected || 'No answer (time ran out)';
                const block = document.createElement('div');
                block.className = 'review-block ' + (item.isCorrect ? 'correct' : 'wrong');
                block.innerHTML = `
                    <p class="review-question">${questionNumber}. ${escapeHtml(item.question || '')}</p>
                    <p>Your answer: <span class="review-your-answer ${item.isCorrect ? 'correct' : 'wrong'}">${escapeHtml(yourAnswerText)}</span></p>
                    ${!item.isCorrect ? `<p>Correct answer: <span class="review-correct-answer">${escapeHtml(item.correctAnswer || '')}</span></p>` : ''}
                `;
                resultsReviewList.appendChild(block);
            });

            if (snapshot.mode === 'quiz') return;
            const essayBlock = document.createElement('div');
            essayBlock.className = 'review-block review-essay-block';
            essayBlock.innerHTML = `
                <p class="review-question">Essay: ${escapeHtml(snapshot.essayTopic || '(no topic)')}</p>
                <div class="review-essay-text">${snapshot.essayText ? escapeHtml(snapshot.essayText) : 'Not submitted.'}</div>
            `;
            resultsReviewList.appendChild(essayBlock);
        }

        function openResultsReview() {
            renderReviewSnapshot(buildCurrentReviewSnapshot());
            resultsReviewOverlay.classList.remove('hidden');
        }

        function closeResultsReview() {
            resultsReviewOverlay.classList.add('hidden');
        }

        /* Account system: Join / Sign In / Sign Up, backed by auth.php + Maten DB (testcom_users table) */

        function syncAccountBarVisibility() {
            accountBar.classList.toggle('hidden', startScreen.classList.contains('hidden') || !authStatusReady);
        }

        function openAuthScreen() {
            authOverlay.classList.remove('hidden');
            showAuthChoice();
        }

        function closeAuthScreen() {
            authOverlay.classList.add('hidden');
        }

        function showAuthChoice() {
            authChoiceScreen.classList.remove('hidden');
            authSigninScreen.classList.add('hidden');
            authSignupScreen.classList.add('hidden');
        }

        function showAuthForm(which) {
            authChoiceScreen.classList.add('hidden');
            authSigninScreen.classList.toggle('hidden', which !== 'signin');
            authSignupScreen.classList.toggle('hidden', which !== 'signup');
            document.getElementById('signin-error').innerText = '';
            document.getElementById('signup-error').innerText = '';
        }

        function setLoggedInUI(email, role) {
            joinBtn.classList.add('hidden');
            accountInfo.classList.remove('hidden');
            const name = getSavedNickname();
            accountEmailDisplay.innerText = name ? `${email} (${name})` : email;
            currentUserEmail = email;
            currentUserRole = role || 'user';
            adminPanelMenuBtn.classList.toggle('hidden', currentUserRole !== 'admin');
            if (!historyPage.classList.contains('hidden')) loadTestHistory();
            syncAdminCopyButton();
            if (name) {
                syncNicknameForAccount(name);
            }
        }

        function setLoggedOutUI() {
            joinBtn.classList.remove('hidden');
            accountInfo.classList.add('hidden');
            currentUserEmail = '';
            currentUserRole = 'guest';
            adminPanelMenuBtn.classList.add('hidden');
            if (!historyPage.classList.contains('hidden')) loadTestHistory();
            syncAdminCopyButton();
        }

        async function checkAuthStatus() {
            try {
                const res = await fetch('auth.php?action=me', { credentials: 'same-origin' });
                const data = await res.json();
                if (data.loggedIn) {
                    setLoggedInUI(data.email, data.role);
                } else {
                    setLoggedOutUI();
                }
            } catch (e) {
                setLoggedOutUI();
            }
            authStatusReady = true;
            syncAccountBarVisibility();
        }
        checkAuthStatus();

        async function submitSignUp() {
            const email = document.getElementById('signup-email').value.trim();
            const password = document.getElementById('signup-password').value;
            const password2 = document.getElementById('signup-password2').value;
            const errorEl = document.getElementById('signup-error');
            errorEl.innerText = '';
            try {
                const res = await fetch('auth.php?action=register', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email, password, password2 })
                });
                const data = await res.json();
                if (!res.ok) {
                    errorEl.innerText = data.error || 'Registration failed.';
                    return;
                }
                setLoggedInUI(data.email, data.role);
                closeAuthScreen();
            } catch (e) {
                errorEl.innerText = 'Network error. Please try again.';
            }
        }

        async function submitSignIn() {
            const email = document.getElementById('signin-email').value.trim();
            const password = document.getElementById('signin-password').value;
            const errorEl = document.getElementById('signin-error');
            errorEl.innerText = '';
            try {
                const res = await fetch('auth.php?action=login', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email, password })
                });
                const data = await res.json();
                if (!res.ok) {
                    errorEl.innerText = data.error || 'Sign in failed.';
                    return;
                }
                setLoggedInUI(data.email, data.role);
                closeAuthScreen();
            } catch (e) {
                errorEl.innerText = 'Network error. Please try again.';
            }
        }

        async function logoutUser() {
            try {
                await fetch('auth.php?action=logout', { method: 'POST', credentials: 'same-origin' });
            } catch (e) {}
            setLoggedOutUI();
        }

        function answeredQuestionCount() {
            return questionRenderState.filter(state => state && state.answered).length;
        }

        async function recordTestHistory() {
            if (!currentUserEmail || currentUserRole === 'guest') {
                return;
            }
            try {
                await fetch('auth.php?action=record_history', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        score,
                        maxScore: quizDatabase.length * 100,
                        answeredCount: answeredQuestionCount(),
                        totalQuestions: quizDatabase.length,
                        essayWords: countWords(userEssayText),
                        status: quizEndStatus,
                        details: buildCurrentReviewSnapshot()
                    })
                });
            } catch (e) {}
            if (!historyPage.classList.contains('hidden')) {
                loadTestHistory();
            }
        }

        function formatHistoryStatus(status) {
            if (status === 'disqualified') return 'Stopped';
            if (status === 'finished_early') return 'Finished early';
            return 'Completed';
        }

        let selectedHistoryId = null;

        function hideHistoryContextMenu() {
            if (!historyContextMenu) return;
            historyContextMenu.classList.add('hidden');
            selectedHistoryId = null;
        }

        function showHistoryContextMenu(event, item) {
            event.preventDefault();
            event.stopPropagation();
            selectedHistoryId = Number(item.id);
            historyContextMenu.style.left = `${event.clientX}px`;
            historyContextMenu.style.top = `${event.clientY}px`;
            historyContextMenu.classList.remove('hidden');
        }

        document.addEventListener('click', hideHistoryContextMenu);

        async function deleteSelectedHistory() {
            if (!selectedHistoryId) return;
            const id = selectedHistoryId;
            hideHistoryContextMenu();
            askDeleteHistory([id]);
        }

        function getCheckedHistoryIds() {
            return [...document.querySelectorAll('.history-check:checked')]
                .map(input => Number(input.value))
                .filter(Boolean);
        }

        function syncHistorySelectAllState() {
            if (!historySelectAll) return;
            const checks = [...document.querySelectorAll('.history-check')];
            const checked = checks.filter(input => input.checked);
            historySelectAll.checked = checks.length > 0 && checked.length === checks.length;
            historySelectAll.indeterminate = checked.length > 0 && checked.length < checks.length;
            const deleteBtn = document.getElementById('history-delete-selected');
            deleteBtn.disabled = checked.length === 0;
            document.getElementById('history-delete-count').innerText = checked.length ? `(${checked.length})` : '';
        }

        function toggleSelectAllHistory(checked) {
            document.querySelectorAll('.history-check').forEach(input => {
                input.checked = checked;
            });
            syncHistorySelectAllState();
        }

        async function deleteHistoryIds(ids) {
            const uniqueIds = [...new Set(ids)].filter(Boolean);
            for (const id of uniqueIds) {
                try {
                    await fetch('auth.php?action=delete_history', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id })
                    });
                } catch (e) {}
            }
        }

        async function deleteCheckedHistory() {
            askDeleteHistory(getCheckedHistoryIds());
        }

        let pendingHistoryDeleteIds = [];

        function askDeleteHistory(ids) {
            const unique = [...new Set(ids)].filter(Boolean);
            if (unique.length === 0) return;
            pendingUnfinishedDelete = false;
            document.getElementById('history-delete-subtext').innerText = 'This cannot be undone.';
            pendingHistoryDeleteIds = unique;
            document.getElementById('history-delete-title').innerText =
                unique.length === 1 ? 'Delete this test result?' : `Delete ${unique.length} test results?`;
            document.getElementById('history-delete-overlay').classList.remove('hidden');
        }

        function closeHistoryDeleteConfirm() {
            pendingHistoryDeleteIds = [];
            pendingUnfinishedDelete = false;
            document.getElementById('history-delete-overlay').classList.add('hidden');
        }

        async function confirmHistoryDelete() {
            const ids = pendingHistoryDeleteIds;
            const discardRun = pendingUnfinishedDelete;
            closeHistoryDeleteConfirm();
            if (discardRun) {
                clearRun();
                if (!historyPage.classList.contains('hidden')) loadTestHistory();
                return;
            }
            await deleteHistoryIds(ids);
            loadTestHistory();
        }

        function historyModeLabel(item) {
            try {
                const d = item.details_json ? JSON.parse(item.details_json) : null;
                if (!d || !d.mode) return '';
                const week = String(d.week || '').padStart(2, '0');
                if (d.mode === 'week') return 'Weekly Test - Week ' + week;
                if (d.mode === 'essay') return 'Essay - Week ' + week;
                return 'Quiz';
            } catch (e) { return ''; }
        }

        async function loadTestHistory() {
            await loadServerHistory();
            renderUnfinishedCard();
        }

        async function loadServerHistory() {
            const isGuest = !currentUserEmail || currentUserRole === 'guest';
            document.querySelector('.history-actions').classList.toggle('hidden', isGuest);
            if (isGuest) {
                historyList.innerHTML = '<div class="history-guest"><p>Sign in to save your test results and see them here.</p><button class="btn" onclick="openAuthScreen()">Sign In</button></div>';
                return;
            }
            if (historySelectAll) {
                historySelectAll.checked = false;
                historySelectAll.indeterminate = false;
            }
            historyList.innerHTML = '<p class="history-empty">Loading...</p>';
            try {
                const res = await fetch('auth.php?action=history', { credentials: 'same-origin' });
                const data = await res.json();
                const history = res.ok ? (data.history || []) : [];
                if (history.length === 0) {
                    historyList.innerHTML = '<p class="history-empty">No test history yet.</p>';
                    syncHistorySelectAllState();
                    return;
                }
                historyList.innerHTML = '';
                history.forEach(item => {
                    const row = document.createElement('div');
                    row.className = 'history-row history-card';
                    row.tabIndex = 0;
                    row.setAttribute('role', 'button');
                    const dateLabel = formatSiteDate(item.created_at, true);
                    const correctAnswers = Math.round(Number(item.score) / 100);
                    const totalQuestions = Number(item.total_questions);
                    const isEssayOnly = totalQuestions === 0;
                    const scoreHtml = isEssayOnly
                        ? `<b>${Number(item.essay_words)}</b><span>words written</span>`
                        : `<b>${correctAnswers}</b><span>/ ${totalQuestions} correct answers</span>`;
                    row.innerHTML = `
                        <label class="history-check-wrap" onclick="event.stopPropagation()">
                            <input type="checkbox" class="history-check" value="${Number(item.id)}" onchange="syncHistorySelectAllState()">
                        </label>
                        <div class="history-card-body">
                            <div class="history-card-head">
                                <strong>${escapeHtml(historyModeLabel(item) || 'Quiz')}</strong>
                                <span class="history-badge">${escapeHtml(formatHistoryStatus(item.status))}</span>
                            </div>
                            <div class="history-card-user">${escapeHtml(getSavedNickname() || 'Guest')}${currentUserEmail ? ` <small>${escapeHtml(currentUserEmail)}</small>` : ''}</div>
                            <div class="history-card-score">${scoreHtml}</div>
                            <div class="history-card-meta">${escapeHtml(dateLabel)}${isEssayOnly ? '' : ` &middot; ${Number(item.answered_count)} / ${totalQuestions} answered &middot; ${Number(item.essay_words)} words`}</div>
                        </div>
                        <button type="button" class="history-trash-btn" aria-label="Delete this result">${ICON_TRASH}</button>
                    `;
                    row.querySelector('.history-trash-btn').addEventListener('click', event => {
                        event.stopPropagation();
                        askDeleteHistory([Number(item.id)]);
                    });
                    row.onclick = () => openHistoryResult(item);
                    row.oncontextmenu = (event) => showHistoryContextMenu(event, item);
                    row.onkeydown = (event) => {
                        if (event.key === 'Enter' || event.key === ' ') {
                            event.preventDefault();
                            openHistoryResult(item);
                        }
                    };
                    historyList.appendChild(row);
                });
                syncHistorySelectAllState();
            } catch (e) {
                historyList.innerHTML = '<p class="history-empty">Could not load history.</p>';
            }
        }

        function openHistoryModal() {
            if (menuOpen) toggleMenu();
            nicknameScreen.classList.add('hidden');
            startScreen.classList.add('hidden');
            quizScreen.classList.add('hidden');
            essayScreen.classList.add('hidden');
            resultScreen.classList.add('hidden');
            closeLobby();
            adminPanelPage.classList.add('hidden');
            usersPage.classList.add('hidden');
            historyPage.classList.remove('hidden');
            syncAccountBarVisibility();
            syncFinishButtonVisibility();
            loadTestHistory();
        }

        function closeHistoryPage() {
            historyPage.classList.add('hidden');
            startScreen.classList.remove('hidden');
            syncAccountBarVisibility();
        }

        let historyResultItem = null;
        let historyResultSnapshot = null;

        function openHistoryResult(item) {
            let snapshot = null;
            try {
                snapshot = item.details_json ? JSON.parse(item.details_json) : null;
            } catch (e) {
                snapshot = null;
            }
            historyResultItem = item;
            historyResultSnapshot = snapshot;

            const mode = snapshot && snapshot.mode ? snapshot.mode : 'quiz';
            const week = String((snapshot && snapshot.week) || '').padStart(2, '0');
            const total = Number(item.total_questions);
            const correct = Math.round(Number(item.score) / 100);
            const hasQuiz = total > 0;
            const hasEssay = mode !== 'quiz' || Number(item.essay_words) > 0;
            document.getElementById('hr-mode').textContent =
                mode === 'week' ? 'Weekly Test - Week ' + week : mode === 'essay' ? 'Essay - Week ' + week : 'Quiz';
            document.getElementById('hr-date').textContent = formatSiteDate(item.created_at, true);
            fillUserInfo(document.getElementById('hr-user-name'), document.getElementById('hr-user-email'));

            document.getElementById('hr-count-wrap').classList.toggle('hidden', !hasQuiz);
            const scoreEl = document.getElementById('hr-score');
            scoreEl.classList.toggle('hidden', !hasQuiz);
            scoreEl.textContent = `Quiz Score: ${Number(item.score)} / ${Number(item.max_score)}`;

            const statusEl = document.getElementById('hr-essay-status');
            const essayText = snapshot && snapshot.essayText ? snapshot.essayText : '';
            statusEl.classList.toggle('hidden', !(hasEssay || item.status === 'disqualified'));
            statusEl.textContent = item.status === 'disqualified'
                ? 'Test stopped: 3 warnings were reached.'
                : (Number(item.essay_words) > 0 ? `Essay Status: Submitted (${Number(item.essay_words)} words).` : 'Essay Status: Not submitted / Empty.');
            document.getElementById('hr-download-btn').classList.toggle('hidden', !essayText || item.status === 'disqualified');

            document.getElementById('history-result-overlay').classList.remove('hidden');
            if (hasQuiz) animateCorrectCount(correct, document.getElementById('hr-count'));
        }

        function closeHistoryResult() {
            document.getElementById('history-result-overlay').classList.add('hidden');
        }

        function openHistoryDetails() {
            const snapshot = historyResultSnapshot;
            if (!snapshot || !Array.isArray(snapshot.questions)) {
                resultsReviewList.innerHTML = '<p class="history-empty">Full answers were not saved for this older test.</p>';
            } else {
                renderReviewSnapshot(snapshot);
            }
            resultsReviewOverlay.classList.remove('hidden');
        }

        function downloadHistoryEssay() {
            const text = historyResultSnapshot && historyResultSnapshot.essayText;
            downloadEssayText(text);
        }

        const ADMIN_COPY_KEY = 'adminCopyEnabled';
        let adminCopyEnabled = false;

        /* --- Site language (admin-only switch). Covers static UI chrome; quiz
           questions and essay topics stay in English. --- */
        const TRANSLATIONS = {
            en: {
                nickname_title: "Kazakhstan History Quiz & Essay", nickname_subtitle: "Enter your nickname to begin",
                nickname_placeholder: "Your nickname", nickname_ready: "READY!",
                start_title: "Kazakhstan History Quiz & Essay",
                start_subtitle: "English quiz (15 sec per question) + Final essay (20 minutes)", start_button: "START QUIZ", mode_start: "START",
                menu_title: "Menu", menu_home: "Home", menu_toggle_sound: "Toggle Sound", menu_theme_label: "Design", menu_rename: "Rename", menu_promo: "Promo Code", menu_games: "Games",
                menu_admin_panel: "Admin Panel", menu_restart: "Restart",
                design_modal_title: "Choose Design", design_modal_subtitle: "Pick a look for the site.",
                essay_title: "Final Task: Historical Essay", essay_submit: "SUBMIT & SAVE ESSAY",
                result_play_again: "PLAY AGAIN", result_view_result: "View Result", result_download_essay: "Download Essay",
                auth_welcome: "Welcome", auth_signin: "Sign In", auth_signup: "Sign Up",
                auth_email_placeholder: "Email", auth_password_placeholder: "Password",
                auth_repeat_password_placeholder: "Repeat password", auth_back: "Back",
                admin_panel_title: "Admin Panel", admin_settings_title: "Admin Settings", admin_users_title: "Users",
                admin_back: "Back", admin_settings_button: "Admin Settings", admin_users_button: "Users",
                admin_language_label: "Site Language", admin_test_guard_button: "Test Guard", admin_copy_button: "Admin Copy", admin_show_answers_button: "Show All Correct Answers",
                users_registered_label: "Registered", users_admin_label: "Admins", users_guest_label: "Guest",
                users_tab_users: "Users", users_tab_admins: "Admins", users_tab_guest: "Guest", users_search_placeholder: "Search by nickname or email",
                promo_title: "Promo Code", promo_placeholder: "Enter promo code", promo_redeem: "Redeem", cancel: "Cancel",
                history_title: "Test History", delete: "Delete"
            },
            kk: {
                nickname_title: "Қазақстан тарихы бойынша тест және эссе", nickname_subtitle: "Бастау үшін ник атыңызды енгізіңіз",
                nickname_placeholder: "Сіздің атыңыз", nickname_ready: "ДАЙЫН!",
                start_title: "Қазақстан тарихы бойынша тест және эссе",
                start_subtitle: "Ағылшын тіліндегі тест (сұраққа 15 сек) + Соңғы эссе (20 минут)", start_button: "ТЕСТТІ БАСТАУ", mode_start: "БАСТАУ",
                menu_title: "Мәзір", menu_home: "Главный", menu_toggle_sound: "Дыбысты қосу/өшіру", menu_theme_label: "Оформление", menu_rename: "Атын өзгерту", menu_promo: "Промокод", menu_games: "Ойындар",
                menu_admin_panel: "Админ панелі", menu_restart: "Қайта бастау",
                design_modal_title: "Дизайн таңдау", design_modal_subtitle: "Сайтқа ұнайтын көріністі таңдаңыз.",
                essay_title: "Соңғы тапсырма: тарихи эссе", essay_submit: "ЭССЕНІ ЖІБЕРУ",
                result_play_again: "ҚАЙТА ОЙНАУ", result_view_result: "Нәтижені көру", result_download_essay: "Эссені жүктеп алу",
                auth_welcome: "Қош келдіңіз", auth_signin: "Кіру", auth_signup: "Тіркелу",
                auth_email_placeholder: "Электрондық пошта", auth_password_placeholder: "Құпия сөз",
                auth_repeat_password_placeholder: "Құпия сөзді қайталаңыз", auth_back: "Артқа",
                admin_panel_title: "Админ панелі", admin_settings_title: "Админ баптаулары", admin_users_title: "Қолданушылар",
                admin_back: "Артқа", admin_settings_button: "Админ баптаулары", admin_users_button: "Қолданушылар",
                admin_language_label: "Сайт тілі", admin_test_guard_button: "Тест қорғанысы", admin_copy_button: "Админге көшіру", admin_show_answers_button: "Барлық дұрыс жауапты көрсету",
                users_registered_label: "Тіркелген", users_admin_label: "Админдер", users_guest_label: "Қонақ",
                users_tab_users: "Қолданушылар", users_tab_admins: "Админдер", users_tab_guest: "Қонақ", users_search_placeholder: "Ник аты немесе email арқылы іздеу",
                promo_title: "Промокод", promo_placeholder: "Промокодты енгізіңіз", promo_redeem: "Қолдану", cancel: "Бас тарту",
                history_title: "Тест тарихы", delete: "Өшіру"
            },
            ru: {
                nickname_title: "Тест и эссе по истории Казахстана", nickname_subtitle: "Введите ник, чтобы начать",
                nickname_placeholder: "Ваш ник", nickname_ready: "ГОТОВО!",
                start_title: "Тест и эссе по истории Казахстана",
                start_subtitle: "Тест на английском (15 сек на вопрос) + Финальное эссе (20 минут)", start_button: "НАЧАТЬ ТЕСТ", mode_start: "СТАРТ",
                menu_title: "Меню", menu_home: "Главный", menu_toggle_sound: "Вкл/выкл звук", menu_theme_label: "Оформление", menu_rename: "Изменить имя", menu_promo: "Промокод", menu_games: "Игры",
                menu_admin_panel: "Админ-панель", menu_restart: "Начать заново",
                design_modal_title: "Выбор дизайна", design_modal_subtitle: "Выберите оформление сайта.",
                essay_title: "Финальное задание: историческое эссе", essay_submit: "ОТПРАВИТЬ ЭССЕ",
                result_play_again: "ИГРАТЬ СНОВА", result_view_result: "Посмотреть результат", result_download_essay: "Скачать эссе",
                auth_welcome: "Добро пожаловать", auth_signin: "Войти", auth_signup: "Регистрация",
                auth_email_placeholder: "Email", auth_password_placeholder: "Пароль",
                auth_repeat_password_placeholder: "Повторите пароль", auth_back: "Назад",
                admin_panel_title: "Админ-панель", admin_settings_title: "Настройки админа", admin_users_title: "Пользователи",
                admin_back: "Назад", admin_settings_button: "Настройки админа", admin_users_button: "Пользователи",
                admin_language_label: "Язык сайта", admin_test_guard_button: "Защита теста", admin_copy_button: "Копирование для админа", admin_show_answers_button: "Показать все правильные ответы",
                users_registered_label: "Зарегистрировано", users_admin_label: "Админы", users_guest_label: "Гость",
                users_tab_users: "Пользователи", users_tab_admins: "Админы", users_tab_guest: "Гость", users_search_placeholder: "Поиск по нику или email",
                promo_title: "Промокод", promo_placeholder: "Введите промокод", promo_redeem: "Применить", cancel: "Отмена",
                history_title: "История тестов", delete: "Удалить"
            }
        };

        function applyLanguage(lang) {
            const dict = TRANSLATIONS[lang] || TRANSLATIONS.en;
            document.querySelectorAll('[data-i18n]').forEach(el => {
                const key = el.getAttribute('data-i18n');
                if (dict[key]) el.textContent = dict[key];
            });
            document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
                const key = el.getAttribute('data-i18n-placeholder');
                if (dict[key]) el.setAttribute('placeholder', dict[key]);
            });
            document.querySelectorAll('[id^="lang-btn-"]').forEach(btn => {
                btn.classList.toggle('active', btn.id === `lang-btn-${lang}`);
            });
            try { localStorage.setItem('siteLanguage', lang); } catch (e) {}
            syncTestGuardButton();
            syncAdminCopyButton();
        }

        function setSiteLanguage(lang) {
            applyLanguage(lang);
        }

        (function initLanguage() {
            let saved = 'en';
            try { saved = localStorage.getItem('siteLanguage') || 'en'; } catch (e) {}
            applyLanguage(saved);
        })();

        /* --- Promo code --- */
        function openPromoModal() {
            if (menuOpen) toggleMenu();
            promoInput.value = '';
            promoError.innerText = '';
            promoOverlay.classList.remove('hidden');
        }

        function closePromoModal() {
            promoOverlay.classList.add('hidden');
        }

        async function submitPromoCode() {
            const code = promoInput.value.trim();
            promoError.innerText = '';
            try {
                const res = await fetch('auth.php?action=redeem_promo', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ code })
                });
                const data = await res.json();
                if (!res.ok) {
                    promoError.innerText = data.error || 'Could not redeem promo code.';
                    return;
                }
                currentUserRole = data.role;
                adminPanelMenuBtn.classList.toggle('hidden', currentUserRole !== 'admin');
                syncAdminCopyButton();
                closePromoModal();
            } catch (e) {
                promoError.innerText = 'Network error. Please try again.';
            }
        }

        /* --- Admin Panel (full page) --- */
        function openAdminPanel() {
            if (menuOpen) toggleMenu();
            adminPanelPage.classList.remove('hidden');
        }

        function closeAdminPanel() {
            adminPanelPage.classList.add('hidden');
        }

        /* --- Admin Settings modal --- */
        function openAdminSettings() {
            adminSettingsOverlay.classList.remove('hidden');
        }

        function closeAdminSettings() {
            adminSettingsOverlay.classList.add('hidden');
        }

        function syncAdminCopyButton() {
            const lang = (() => {
                try { return localStorage.getItem('siteLanguage') || 'en'; } catch (e) { return 'en'; }
            })();
            const base = (TRANSLATIONS[lang] || TRANSLATIONS.en).admin_copy_button;
            toggleAdminCopyBtn.innerText = `${base}: ${adminCopyEnabled ? 'ON' : 'OFF'}`;
            toggleAdminCopyBtn.classList.toggle('active-toggle', adminCopyEnabled);
            document.body.classList.toggle('admin-copy-enabled', canAdminCopyText());
        }

        function toggleAdminCopy() {
            adminCopyEnabled = !adminCopyEnabled;
            try { localStorage.setItem(ADMIN_COPY_KEY, adminCopyEnabled ? '1' : '0'); } catch (e) {}
            syncAdminCopyButton();
        }

        (function initAdminCopySetting() {
            try {
                adminCopyEnabled = localStorage.getItem(ADMIN_COPY_KEY) === '1';
            } catch (e) {
                adminCopyEnabled = false;
            }
            syncAdminCopyButton();
        })();

        function syncTestGuardButton() {
            const lang = (() => {
                try { return localStorage.getItem('siteLanguage') || 'en'; } catch (e) { return 'en'; }
            })();
            const base = (TRANSLATIONS[lang] || TRANSLATIONS.en).admin_test_guard_button;
            toggleTestGuardBtn.innerText = `${base}: ${testGuardEnabled ? 'ON' : 'OFF'}`;
            toggleTestGuardBtn.classList.toggle('active-toggle', testGuardEnabled);
        }

        function toggleTestGuard() {
            testGuardEnabled = !testGuardEnabled;
            try { localStorage.setItem(TEST_GUARD_KEY, testGuardEnabled ? '1' : '0'); } catch (e) {}
            if (!testGuardEnabled) {
                stopTestGuardSession();
            }
            syncTestGuardButton();
        }

        (function initTestGuardSetting() {
            try {
                testGuardEnabled = localStorage.getItem(TEST_GUARD_KEY) !== '0';
            } catch (e) {
                testGuardEnabled = true;
            }
            syncTestGuardButton();
        })();

        let showAllAnswers = false;
        function toggleShowAllAnswers() {
            showAllAnswers = !showAllAnswers;
            toggleShowAnswersBtn.classList.toggle('active-toggle', showAllAnswers);
            if (!quizScreen.classList.contains('hidden') && reviewIndex === null) {
                renderCurrentLiveState();
            }
        }

        /* --- Users page (full page) --- */
        let usersTab = 'users';
        let usersCache = [];
        let adminsCache = [];
        let guestsCache = [];

        function openUsersPage() {
            usersSearchInput.value = '';
            switchUsersTab('users');
            loadCounts();
            usersPage.classList.remove('hidden');
        }

        function closeUsersPage() {
            usersPage.classList.add('hidden');
        }

        async function loadCounts() {
            try {
                const res = await fetch('auth.php?action=counts', { credentials: 'same-origin' });
                const data = await res.json();
                if (res.ok) {
                    registeredCountEl.innerText = data.userCount;
                    adminCountEl.innerText = data.adminCount || 0;
                    guestCountEl.innerText = data.guestCount;
                }
            } catch (e) {}
        }

        async function switchUsersTab(tab) {
            usersTab = tab;
            tabUsersBtn.classList.toggle('active', tab === 'users');
            tabAdminsBtn.classList.toggle('active', tab === 'admins');
            tabGuestsBtn.classList.toggle('active', tab === 'guest');
            usersSearchInput.value = '';
            if (tab === 'users') {
                await loadUsersList();
            } else if (tab === 'admins') {
                await loadAdminsList();
            } else {
                await loadGuestsList();
            }
        }

        async function loadUsersList() {
            try {
                const res = await fetch('auth.php?action=list_users', { credentials: 'same-origin' });
                const data = await res.json();
                usersCache = res.ok ? (data.users || []) : [];
            } catch (e) {
                usersCache = [];
            }
            renderUsersList();
        }

        async function loadGuestsList() {
            try {
                const res = await fetch('auth.php?action=list_guests', { credentials: 'same-origin' });
                const data = await res.json();
                guestsCache = res.ok ? (data.guests || []) : [];
            } catch (e) {
                guestsCache = [];
            }
            renderUsersList();
        }

        async function loadAdminsList() {
            try {
                const res = await fetch('auth.php?action=list_admins', { credentials: 'same-origin' });
                const data = await res.json();
                adminsCache = res.ok ? (data.admins || []) : [];
            } catch (e) {
                adminsCache = [];
            }
            renderUsersList();
        }

        function renderUsersList() {
            const query = usersSearchInput.value.trim().toLowerCase();
            usersListEl.innerHTML = '';
            const source = usersTab === 'users' ? usersCache : (usersTab === 'admins' ? adminsCache : guestsCache);
            const filtered = source.filter(item => {
                const label = usersTab === 'guest' ? item.nickname : `${item.email} ${item.nickname || ''}`;
                return label.toLowerCase().includes(query);
            });
            filtered.forEach(item => {
                const row = document.createElement('div');
                row.className = 'user-row';
                if (usersTab === 'users' || usersTab === 'admins') {
                    const nicknamePart = item.nickname ? ` <span class="user-row-nickname">(${escapeHtml(item.nickname)})</span>` : '';
                    row.innerHTML = `<span class="user-row-name">${escapeHtml(item.email)}${nicknamePart}</span><span class="user-row-role">${escapeHtml(item.role)}</span>`;
                } else {
                    row.innerHTML = `<span class="user-row-name">${escapeHtml(item.nickname)}</span>`;
                }
                usersListEl.appendChild(row);
            });
            if (filtered.length === 0) {
                usersListEl.innerHTML = '<p class="users-empty">No results.</p>';
            }
        }

        function filterUsersList() {
            renderUsersList();
        }

        /* ---------- Loading screen and the "lights on" reveal of the home modes ---------- */
        let siteReady = false;

        function playModeReveal() {
            if (!siteReady || startScreen.classList.contains('hidden')) return;
            modeStage.classList.remove('lit');
            void modeStage.offsetWidth;
            setTimeout(() => modeStage.classList.add('lit'), 250);
        }

        new MutationObserver(() => {
            updateModeMetrics();
            refreshResumeNote();
            playModeReveal();
        }).observe(startScreen, { attributes: true, attributeFilter: ['class'] });

        (function initLoader() {
            const loader = document.getElementById('site-loader');
            const fill = document.getElementById('loader-bar-fill');
            const images = [...document.querySelectorAll('.mode-card img, .maten-watermark img')];
            const total = images.length;
            let done = 0;
            const step = () => { done++; fill.style.width = Math.round(done / total * 100) + '%'; };
            const imageReady = img => new Promise(resolve => {
                const finish = () => resolve();
                if (img.complete && img.naturalWidth) return finish();
                img.addEventListener('load', finish, { once: true });
                img.addEventListener('error', finish, { once: true });
            }).then(() => (img.decode ? img.decode().catch(() => {}) : null));
            /* Only the pictures are awaited: the 9 MB music file must not hold the site back */
            const assets = Promise.all(images.map(img => imageReady(img).then(step)));
            const minimum = new Promise(resolve => setTimeout(resolve, 1300));
            const giveUp = new Promise(resolve => setTimeout(resolve, 10000));
            Promise.race([Promise.all([assets, minimum]), giveUp]).then(() => {
                fill.style.width = '100%';
                setTimeout(() => {
                    loader.classList.add('done');
                    siteReady = true;
                    updateModeMetrics();
                    refreshResumeNote();
                    playModeReveal();
                    setTimeout(() => loader.remove(), 700);
                }, 250);
            });
        })();
    </script>
</body>
</html>
