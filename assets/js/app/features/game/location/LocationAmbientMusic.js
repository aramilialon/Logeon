const gw = (typeof window !== 'undefined') ? window : globalThis;

function isYouTubeUrl(url) {
    return /(?:youtube\.com|youtu\.be|music\.youtube\.com)/i.test(String(url || ''));
}

function extractYouTubeId(url) {
    var str = String(url || '');
    var pats = [
        /[?&]v=([a-zA-Z0-9_-]{11})/,
        /youtu\.be\/([a-zA-Z0-9_-]{11})/,
        /\/embed\/([a-zA-Z0-9_-]{11})/,
        /\/shorts\/([a-zA-Z0-9_-]{11})/,
        /\/v\/([a-zA-Z0-9_-]{11})/,
    ];
    for (var i = 0; i < pats.length; i++) {
        var m = str.match(pats[i]);
        if (m) return m[1];
    }
    return null;
}

function showToast(body, type) {
    if (gw.Toast && typeof gw.Toast.show === 'function') {
        gw.Toast.show({ body: body, type: type || 'info' });
    }
}

const LocationAmbientMusic = {
    _state: null,
    _stateReceivedAtMs: 0,
    _localMuted: false,
    _audioEl: null,
    _audioNeedsSync: false,
    _audioEventsBound: false,
    _ytPlayer: null,
    _ytApiReady: false,
    _ytApiLoading: false,
    _ytPendingVideoId: null,
    _ytNeedsSync: false,
    _currentVideoId: null,
    _els: null,
    _bound: false,

    init: function (state) {
        var doc = gw.document;
        if (!doc) return;

        this._audioEl = doc.getElementById('location-ambient-music-player');
        this._els = {
            bar: doc.getElementById('location-music-bar'),
            title: doc.getElementById('location-music-bar-title'),
            statusBadge: doc.getElementById('location-music-bar-status'),
            playBtn: doc.getElementById('location-music-play-btn'),
            muteBtn: doc.getElementById('location-music-mute-btn'),
            muteAllBtn: doc.getElementById('location-music-muteall-btn'),
            stopBtn: doc.getElementById('location-music-stop-btn')
        };

        this._bindAudioEvents();
        this._bindUI();
        this.setState(state && typeof state === 'object' ? state : { is_active: false });
    },

    _bindAudioEvents: function () {
        if (this._audioEventsBound || !this._audioEl || typeof this._audioEl.addEventListener !== 'function') {
            return;
        }
        this._audioEventsBound = true;
        var self = this;
        this._audioEl.addEventListener('loadedmetadata', function () {
            self._syncAudioToSharedOffset();
        });
        this._audioEl.addEventListener('durationchange', function () {
            self._syncAudioToSharedOffset();
        });
    },

    _findEls: function () {
        var doc = gw.document;
        if (!doc || !this._els) return;
        var ids = {
            bar: 'location-music-bar',
            title: 'location-music-bar-title',
            statusBadge: 'location-music-bar-status',
            playBtn: 'location-music-play-btn',
            muteBtn: 'location-music-mute-btn',
            muteAllBtn: 'location-music-muteall-btn',
            stopBtn: 'location-music-stop-btn'
        };
        var changed = false;
        for (var k in ids) {
            if (ids.hasOwnProperty(k) && !this._els[k]) {
                var el = doc.getElementById(ids[k]);
                if (el) {
                    this._els[k] = el;
                    changed = true;
                }
            }
        }
        if (changed) this._bindUI();
    },

    setState: function (state) {
        this._findEls();
        var oldState = this._state;
        this._state = (state && typeof state === 'object') ? state : { is_active: false };
        this._stateReceivedAtMs = Date.now();

        if (!this._state.is_active) {
            this._audioNeedsSync = false;
            this._ytNeedsSync = false;
            this._stopAll();
            this._hideBar();
            return;
        }

        this._showBar();
        this._updateTitle();

        if (this._hasActivePlayer() && this._isSameSource(oldState, this._state)) {
            this._applyGlobalMute(!!this._state.force_muted);
            return;
        }

        this._startPlayback();
    },

    _hasActivePlayer: function () {
        if (this._ytPlayer) return true;
        if (this._ytPendingVideoId) return true;
        if (this._audioEl && this._audioEl.src) return true;
        return false;
    },

    _getStateStartMarker: function (state) {
        if (!state || typeof state !== 'object') return '';
        var startedAtTs = parseInt(String(state.started_at_ts || ''), 10);
        if (isFinite(startedAtTs) && startedAtTs > 0) {
            return 'ts:' + String(startedAtTs);
        }
        var startedAt = String(state.started_at || '').trim();
        if (startedAt !== '') {
            return 'dt:' + startedAt;
        }
        return '';
    },

    _isSameSource: function (oldState, newState) {
        if (!oldState || !newState) return false;
        if (!oldState.is_active) return false;
        if (oldState.source_type !== newState.source_type) return false;

        var oldMarker = this._getStateStartMarker(oldState);
        var newMarker = this._getStateStartMarker(newState);
        if ((oldMarker !== '' || newMarker !== '') && oldMarker !== newMarker) {
            return false;
        }

        if (oldState.source_type === 'youtube') {
            var oldVid = oldState.youtube_video_id || extractYouTubeId(oldState.source_url || '');
            var newVid = newState.youtube_video_id || extractYouTubeId(newState.source_url || '');
            return !!oldVid && oldVid === newVid;
        }
        return !!oldState.source_url && oldState.source_url === newState.source_url;
    },

    _parseStartedAtTs: function (state) {
        if (!state || typeof state !== 'object') return 0;

        var rawTs = parseInt(String(state.started_at_ts || ''), 10);
        if (isFinite(rawTs) && rawTs > 0) {
            return rawTs;
        }

        var raw = String(state.started_at || '').trim();
        if (raw === '') {
            return 0;
        }

        var parsed = Date.parse(raw.replace(' ', 'T'));
        if (!isFinite(parsed) || parsed <= 0) {
            return 0;
        }

        return Math.floor(parsed / 1000);
    },

    _getSharedOffsetSeconds: function () {
        var state = this._state;
        if (!state || !state.is_active) {
            return null;
        }

        var startedAtTs = this._parseStartedAtTs(state);
        if (!isFinite(startedAtTs) || startedAtTs <= 0) {
            return null;
        }

        var serverNowTs = parseInt(String(state.server_now_ts || ''), 10);
        if (isFinite(serverNowTs) && serverNowTs > 0 && this._stateReceivedAtMs > 0) {
            var baseElapsed = Math.max(0, serverNowTs - startedAtTs);
            var clientElapsed = Math.max(0, (Date.now() - this._stateReceivedAtMs) / 1000);
            return baseElapsed + clientElapsed;
        }

        return Math.max(0, (Date.now() / 1000) - startedAtTs);
    },

    _normalizeLoopOffset: function (offsetSeconds, durationSeconds) {
        var offset = Number(offsetSeconds || 0);
        var duration = Number(durationSeconds || 0);
        if (!isFinite(offset) || offset < 0) {
            offset = 0;
        }
        if (!isFinite(duration) || duration <= 0) {
            return offset;
        }
        return offset % duration;
    },

    _syncAudioToSharedOffset: function () {
        if (!this._audioEl || !this._audioNeedsSync) {
            return;
        }

        var offset = this._getSharedOffsetSeconds();
        if (offset === null) {
            this._audioNeedsSync = false;
            return;
        }

        var duration = Number(this._audioEl.duration || 0);
        if (!isFinite(duration) || duration <= 0) {
            return;
        }

        var target = this._normalizeLoopOffset(offset, duration);
        try {
            this._audioEl.currentTime = target;
            this._audioNeedsSync = false;
        } catch (e) {}
    },

    _syncYouTubeToSharedOffset: function (retryCount) {
        if (!this._ytPlayer || !this._ytNeedsSync) {
            return;
        }

        var offset = this._getSharedOffsetSeconds();
        if (offset === null) {
            this._ytNeedsSync = false;
            return;
        }

        var duration = 0;
        try {
            duration = Number(this._ytPlayer.getDuration() || 0);
        } catch (e) {
            duration = 0;
        }

        if (!isFinite(duration) || duration <= 0) {
            var attempts = parseInt(String(retryCount || 0), 10) || 0;
            if (attempts < 8) {
                var self = this;
                gw.setTimeout(function () {
                    self._syncYouTubeToSharedOffset(attempts + 1);
                }, 350);
            }
            return;
        }

        var target = this._normalizeLoopOffset(offset, duration);
        try {
            this._ytPlayer.seekTo(target, true);
            this._ytNeedsSync = false;
        } catch (e) {}
    },

    _applyGlobalMute: function (forceMuted) {
        if (this._ytPlayer) {
            try {
                if (forceMuted || this._localMuted) {
                    this._ytPlayer.mute();
                } else {
                    this._ytPlayer.unMute();
                    this._ytPlayer.setVolume(100);
                }
            } catch (e) {}
        } else if (this._audioEl && this._audioEl.src) {
            this._audioEl.muted = forceMuted || this._localMuted;
        }

        if (forceMuted) {
            this._setStatus('muted-global');
        } else if (this._localMuted) {
            this._setStatus('muted-local');
        } else {
            this._setStatus('playing');
        }
        this._hidePlayBtn();
        this._updateMuteBtn();
    },

    _startPlayback: function () {
        var s = this._state;
        if (!s || !s.is_active) return;

        if (s.source_type === 'youtube') {
            var vid = s.youtube_video_id || extractYouTubeId(s.source_url || '');
            if (vid) {
                this._playYouTube(vid);
            } else {
                this._setStatus('error');
            }
        } else if (s.source_url) {
            this._playAudio(s.source_url);
        } else {
            this._setStatus('error');
        }
    },

    _playAudio: function (url) {
        this._destroyYTPlayer();
        if (!this._audioEl) {
            this._setStatus('error');
            return;
        }

        if ((this._audioEl.getAttribute('src') || '') !== url) {
            this._audioEl.src = url;
        }
        this._audioEl.volume = 1.0;
        this._audioEl.muted = this._localMuted || !!(this._state && this._state.force_muted);
        this._audioNeedsSync = true;
        if (this._audioEl.readyState >= 1) {
            this._syncAudioToSharedOffset();
        }

        var self = this;
        var p = this._audioEl.play();
        if (p && typeof p.then === 'function') {
            p.then(function () {
                self._syncAudioToSharedOffset();
                var s = self._state;
                if (s && s.force_muted) {
                    self._setStatus('muted-global');
                } else {
                    self._setStatus(self._localMuted ? 'muted-local' : 'playing');
                }
                self._hidePlayBtn();
                self._updateMuteBtn();
            }).catch(function () {
                self._setStatus('autoplay-blocked');
                self._showPlayBtn();
            });
        } else {
            this._syncAudioToSharedOffset();
            var state = this._state;
            if (state && state.force_muted) {
                this._setStatus('muted-global');
            } else {
                this._setStatus(this._localMuted ? 'muted-local' : 'playing');
            }
            this._hidePlayBtn();
            this._updateMuteBtn();
        }
    },

    _stopAudio: function () {
        if (!this._audioEl) return;
        this._audioNeedsSync = false;
        try {
            this._audioEl.pause();
            this._audioEl.removeAttribute('src');
            this._audioEl.load();
        } catch (e) {}
    },

    _playYouTube: function (videoId) {
        this._stopAudio();
        this._ytNeedsSync = true;

        if (this._ytPlayer && this._currentVideoId === videoId) {
            try {
                this._syncYouTubeToSharedOffset();
                if (this._state && this._state.force_muted) {
                    this._ytPlayer.mute();
                } else if (this._localMuted) {
                    this._ytPlayer.mute();
                } else {
                    this._ytPlayer.unMute();
                    this._ytPlayer.setVolume(100);
                }
                this._ytPlayer.playVideo();
            } catch (e) {}
            return;
        }

        this._currentVideoId = videoId;
        this._destroyYTPlayer();
        this._ytNeedsSync = true;

        if (!this._ytApiReady) {
            this._ytPendingVideoId = videoId;
            this._loadYTApi();
            this._setStatus('loading');
            return;
        }

        this._createYTPlayer(videoId);
    },

    _loadYTApi: function () {
        if (this._ytApiReady || this._ytApiLoading) return;
        this._ytApiLoading = true;

        var self = this;
        var prev = gw.onYouTubeIframeAPIReady;
        gw.onYouTubeIframeAPIReady = function () {
            self._ytApiReady = true;
            self._ytApiLoading = false;
            if (typeof prev === 'function') prev();
            if (self._ytPendingVideoId) {
                var id = self._ytPendingVideoId;
                self._ytPendingVideoId = null;
                self._createYTPlayer(id);
            }
        };

        var tag = gw.document.createElement('script');
        tag.src = 'https://www.youtube.com/iframe_api';
        var first = gw.document.getElementsByTagName('script')[0];
        if (first && first.parentNode) {
            first.parentNode.insertBefore(tag, first);
        } else {
            gw.document.head.appendChild(tag);
        }
    },

    _createYTPlayer: function (videoId) {
        this._destroyYTPlayer();
        this._ytNeedsSync = true;

        var container = gw.document && gw.document.getElementById('location-yt-music-container');
        if (!container) {
            this._setStatus('error');
            return;
        }
        container.innerHTML = '';

        var div = gw.document.createElement('div');
        div.id = 'location-yt-player-inner';
        container.appendChild(div);

        var self = this;
        try {
            this._ytPlayer = new gw.YT.Player('location-yt-player-inner', {
                width: 320,
                height: 180,
                videoId: videoId,
                playerVars: {
                    autoplay: 1,
                    mute: 1,
                    loop: 1,
                    playlist: videoId,
                    controls: 0,
                    rel: 0,
                    modestbranding: 1
                },
                events: {
                    onReady: function (e) {
                        self._syncYouTubeToSharedOffset();
                        if (self._state && self._state.force_muted) {
                            self._setStatus('muted-global');
                        } else if (!self._localMuted) {
                            try {
                                e.target.unMute();
                                e.target.setVolume(100);
                            } catch (err) {}
                            self._setStatus('playing');
                        } else {
                            self._setStatus('muted-local');
                        }
                        self._hidePlayBtn();
                        self._updateMuteBtn();
                    },
                    onStateChange: function (e) {
                        if (e.data === 1) {
                            self._syncYouTubeToSharedOffset();
                            if (self._state && self._state.force_muted) {
                                self._setStatus('muted-global');
                            } else {
                                self._setStatus(self._localMuted ? 'muted-local' : 'playing');
                            }
                            self._hidePlayBtn();
                        } else if ((e.data === 2 || e.data === 0) && self._state && self._state.is_active && !self._state.force_muted) {
                            self._setStatus('autoplay-blocked');
                            self._showPlayBtn();
                        }
                    },
                    onError: function () {
                        self._setStatus('error');
                        self._showPlayBtn();
                    }
                }
            });
        } catch (e) {
            this._setStatus('error');
        }
    },

    _destroyYTPlayer: function () {
        if (this._ytPlayer) {
            try { this._ytPlayer.destroy(); } catch (e) {}
            this._ytPlayer = null;
        }
        this._ytNeedsSync = false;
        this._currentVideoId = null;
        var container = gw.document && gw.document.getElementById('location-yt-music-container');
        if (container) container.innerHTML = '';
    },

    _stopAll: function () {
        this._stopAudio();
        this._destroyYTPlayer();
        this._ytPendingVideoId = null;
    },

    _bindUI: function () {
        if (this._bound || !this._els) return;
        this._bound = true;
        var self = this;

        if (this._els.muteBtn) {
            this._els.muteBtn.addEventListener('click', function () { self._toggleLocalMute(); });
        }
        if (this._els.playBtn) {
            this._els.playBtn.addEventListener('click', function () { self._userPlay(); });
        }
        if (this._els.stopBtn) {
            this._els.stopBtn.addEventListener('click', function () {
                self._dispatch('stop');
            });
        }
        if (this._els.muteAllBtn) {
            this._els.muteAllBtn.addEventListener('click', function () {
                var isForceMuted = self._state && self._state.force_muted;
                self._dispatch(isForceMuted ? 'unmute-all' : 'mute-all');
            });
        }
    },

    _toggleLocalMute: function () {
        this._localMuted = !this._localMuted;

        if (this._localMuted) {
            if (this._ytPlayer) {
                try { this._ytPlayer.mute(); } catch (e) {}
            } else if (this._audioEl) {
                this._audioEl.muted = true;
            }
            this._setStatus('muted-local');
        } else {
            if (this._state && this._state.force_muted) {
                this._setStatus('muted-global');
                this._updateMuteBtn();
                return;
            }
            if (this._ytPlayer) {
                try {
                    this._ytPlayer.unMute();
                    this._ytPlayer.setVolume(100);
                } catch (e) {}
            } else if (this._audioEl) {
                this._audioEl.muted = false;
            }
            this._setStatus('playing');
        }
        this._updateMuteBtn();
    },

    _userPlay: function () {
        var state = this._state;
        if (!state || !state.is_active) return;
        this._hidePlayBtn();
        if (this._ytPlayer) {
            try {
                this._ytNeedsSync = true;
                this._syncYouTubeToSharedOffset();
                this._ytPlayer.playVideo();
                if (!this._localMuted && !(this._state && this._state.force_muted)) {
                    this._ytPlayer.unMute();
                    this._ytPlayer.setVolume(100);
                }
            } catch (e) {}
        } else {
            this._startPlayback();
        }
    },

    _dispatch: function (action) {
        var doc = gw.document;
        if (!doc || typeof doc.dispatchEvent !== 'function') return;
        try {
            doc.dispatchEvent(new CustomEvent('locationMusic:staffCommand', { detail: { action: action } }));
        } catch (e) {}
    },

    _showBar: function () {
        if (this._els && this._els.bar) this._els.bar.classList.remove('d-none');
    },

    _hideBar: function () {
        if (this._els && this._els.bar) this._els.bar.classList.add('d-none');
    },

    _showPlayBtn: function () {
        if (this._els && this._els.playBtn) this._els.playBtn.classList.remove('d-none');
    },

    _hidePlayBtn: function () {
        if (this._els && this._els.playBtn) this._els.playBtn.classList.add('d-none');
    },

    _updateTitle: function () {
        var el = this._els && this._els.title;
        if (!el) return;
        var state = this._state;
        if (!state || !state.is_active) {
            el.textContent = '';
            return;
        }
        var title = state.title || '';
        if (!title && state.source_type === 'youtube') {
            title = 'YouTube';
        } else if (!title && state.source_url) {
            try {
                title = new URL(state.source_url).hostname;
            } catch (ex) {
                title = 'Audio';
            }
        }
        el.textContent = title || 'Audio';
    },

    _setStatus: function (status) {
        var el = this._els && this._els.statusBadge;
        if (!el) return;
        el.className = 'location-music-bar__badge';
        var map = {
            playing: ['badge bg-success', 'In riproduzione'],
            'muted-local': ['badge bg-secondary', 'Silenzioso'],
            'muted-global': ['badge bg-warning', 'Silenziato dallo staff'],
            'autoplay-blocked': ['badge bg-info', 'Clicca Avvia'],
            loading: ['badge bg-secondary', 'Caricamento...'],
            error: ['badge bg-danger', 'Errore']
        };
        var entry = map[status];
        if (entry) {
            entry[0].split(' ').forEach(function (cls) {
                el.classList.add(cls);
            });
            el.textContent = entry[1];
        }
    },

    _updateMuteBtn: function () {
        var btn = this._els && this._els.muteBtn;
        if (btn) {
            var icon = btn.querySelector('i');
            if (icon) icon.className = this._localMuted ? 'bi bi-volume-mute-fill' : 'bi bi-volume-up-fill';
            btn.title = this._localMuted ? 'Riattiva audio' : 'Silenzia';
        }

        var btn2 = this._els && this._els.muteAllBtn;
        if (btn2) {
            var forceMuted = this._state && this._state.force_muted;
            var icon2 = btn2.querySelector('i');
            if (icon2) icon2.className = forceMuted ? 'bi bi-volume-up-fill' : 'bi bi-volume-mute-fill';
            btn2.title = forceMuted ? 'Riattiva per tutti' : 'Silenzia per tutti';
        }
    }
};

gw.LocationAmbientMusic = LocationAmbientMusic;
export { LocationAmbientMusic };
export default LocationAmbientMusic;
