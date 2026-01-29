// app.js (Corrected and Reorganized)

document.addEventListener('DOMContentLoaded', () => {
    // --- AUTHENTICATION CHECK ---
    if (localStorage.getItem('isLoggedIn') !== 'true') {
        window.location.href = 'login.html';
        return;
    }

    // --- GLOBAL DOM ELEMENTS ---
    const mainContent = document.getElementById('main-content');
    const audioPlayer = document.getElementById('audio-player');
    const playPauseBtn = document.getElementById('play-pause-btn');
    const nextBtn = document.getElementById('next-btn');
    const prevBtn = document.getElementById('prev-btn');
    const progressBar = document.getElementById('progress-bar');
    const currentTimeEl = document.getElementById('current-time');
    const durationEl = document.getElementById('duration');
    const playerBar = document.getElementById('player-bar');
    const playerTitle = document.getElementById('player-title');
    const playerArtist = document.getElementById('player-artist');
    const themeToggle = document.getElementById('theme-toggle');
    const logoutBtn = document.getElementById('logout-btn');
    const searchBar = document.getElementById('search-bar');
    const shuffleBtn = document.getElementById('shuffle-btn');
    const modalOverlay = document.getElementById('modal-overlay');
    const playlistModal = document.getElementById('playlist-modal');
    const playlistForm = document.getElementById('playlist-form');
    const addToPlaylistModal = document.getElementById('add-to-playlist-modal');
    const contextMenu = document.getElementById('context-menu');

    // --- STATE ---
    let allTracks = [];
    let playlists = [];
    let currentPlaylist = [];
    let trackIndex = 0;
    let isPlaying = false;
    let likedSongs = JSON.parse(localStorage.getItem('likedSongs')) || [];
    let isShuffle = false;
    let songToAdd = null;
    let contextTargetId = null;

    // --- API HELPERS ---
    const api = {
        get: (endpoint) => fetch(endpoint).then(res => res.json()),
        post: (endpoint, body) => fetch(endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) }).then(res => res.json()),
        put: (endpoint, body) => fetch(endpoint, { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) }).then(res => res.json()),
        delete: (endpoint, body) => fetch(endpoint, { method: 'DELETE', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) }).then(res => res.json()),
    };

    // --- ROUTER & NAVIGATION ---
    const router = async () => {
        const routes = {
            '/': { page: 'home-content.html', title: 'Home' },
            '/liked': { page: 'liked-content.html', title: 'Liked Songs' },
            '/playlists': { page: 'playlists-content.html', title: 'Playlists' },
            '/playlists/:id': { page: 'playlist-view.html', title: 'Playlist' }
        };
        const projectBasePath = '/music_app';
        let path = (window.location.pathname.substring(projectBasePath.length) || '/').replace('index.html','');
        let view, params;

        if (path.startsWith('/playlists/')) {
            view = routes['/playlists/:id'];
            params = { id: path.split('/')[2] };
        } else {
            view = routes[path] || routes['/'];
        }

        const html = await fetch(view.page).then(data => data.text());
        mainContent.innerHTML = html;
        document.title = `${view.title} - SoundWave`;

        searchBar.style.display = 'none';
        if (path === '/') {
            searchBar.style.display = 'block';
            displayTracks(allTracks);
        } else if (path === '/liked') {
            displayTracks(allTracks.filter(t => isLiked(t.src)));
        } else if (path === '/playlists') {
            displayPlaylists();
        } else if (params && params.id) {
            const playlist = playlists.find(p => p.id === params.id);
            if (playlist) {
                displayPlaylistView(playlist);
            } else { 
                mainContent.innerHTML = '<h2>Playlist not found</h2>';
            }
        }
        updateNavLinks(path);
    };

    const navigateTo = (path) => {
        const fullPath = `/music_app${path}`;
        history.pushState(null, null, fullPath);
        router();
    };
    
    const updateNavLinks = (currentPath) => {
        document.querySelectorAll('.nav-link').forEach(link => {
            const linkPath = new URL(link.href).pathname.substring('/music_app'.length).replace('index.html', '') || '/';
            if(linkPath === currentPath || (currentPath.startsWith('/playlists') && linkPath === '/playlists')){
                 link.classList.add('active');
            } else {
                 link.classList.remove('active');
            }
        });
    };

    // --- DATA FETCHING & DISPLAY ---
    async function fetchAllTracks() {
        allTracks = await api.get('api_tracks.php').then(files => files.map(file => {
            const title = file.replace(/\.(mp3|wav|ogg|m4a)$/, '').replace(/[-_]/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
            return { title, artist: 'Unknown Artist', src: `sounds/${file}` };
        }));
    }

    async function fetchPlaylists() {
        playlists = await api.get('playlists_api.php');
    }

    function displayTracks(tracksToDisplay, options = {}) {
        const trackListContainer = mainContent.querySelector('#track-list');
        if (!trackListContainer) return;
        currentPlaylist = tracksToDisplay;
        if (!tracksToDisplay || tracksToDisplay.length === 0) {
            trackListContainer.innerHTML = '<p>No tracks to display.</p>';
            return;
        }
        trackListContainer.innerHTML = tracksToDisplay.map((track, index) => {
            const liked = isLiked(track.src);
            const removeButton = options.showRemove ? `<button class="remove-song-btn" data-playlist-id="${options.playlistId}" data-src="${track.src}"><i class="fas fa-times"></i></button>` : '';
            return `
                <div class="track-item" data-src="${track.src}">
                    <div class="track-item-info">
                        <p class="track-item-title">${track.title}</p>
                        <small class="track-item-artist">${track.artist}</small>
                    </div>
                    <div class="track-item-controls">
                        <button class="like-btn" data-src="${track.src}"><i class="${liked ? 'fas' : 'far'} fa-heart"></i></button>
                        <button class="add-to-playlist-btn" data-src="${track.src}"><i class="fas fa-plus"></i></button>
                        <button class="track-play-btn" data-index="${index}"><i class="fas fa-play"></i></button>
                        ${removeButton}
                    </div>
                </div>`;
        }).join('');
        updateTrackItemUI();
    }

    function displayPlaylists() {
        const grid = document.getElementById('playlist-grid');
        grid.innerHTML = playlists.map(p => `
            <div class="playlist-card" data-id="${p.id}">
                <a href="/playlists/${p.id}" data-link>
                    <div class="playlist-card-artwork"><i class="fas fa-music"></i></div>
                    <p class="playlist-card-name">${p.name}</p>
                </a>
            </div>
        `).join('');
    }

    function displayPlaylistView(playlist) {
        document.getElementById('playlist-name-heading').textContent = playlist.name;
        document.getElementById('playlist-song-count').textContent = `${playlist.songs.length} songs`;
        displayTracks(playlist.songs, { showRemove: true, playlistId: playlist.id });
    }

    // --- PLAYER & UI LOGIC ---
    function loadTrack(index, shouldPlay = true) {
        const track = currentPlaylist[index];
        if (!track) return;
        playerTitle.textContent = track.title;
        playerArtist.textContent = track.artist;
        audioPlayer.src = track.src;
        trackIndex = index;
        if (shouldPlay) playTrack();
    }

    function playTrack() {
        playerBar.classList.add('visible');
        isPlaying = true;
        audioPlayer.play();
        playPauseBtn.innerHTML = '<i class="fas fa-pause"></i>';
    }

    function pauseTrack() {
        isPlaying = false;
        audioPlayer.pause();
        playPauseBtn.innerHTML = '<i class="fas fa-play"></i>';
    }

    function updateTrackItemUI() {
        document.querySelectorAll('.track-item').forEach(item => {
            const playBtnIcon = item.querySelector('.track-play-btn i');
            if (!playBtnIcon) return;
            const itemSrc = item.dataset.src;
            playBtnIcon.className = (audioPlayer.src.endsWith(itemSrc) && isPlaying) ? 'fas fa-pause' : 'fas fa-play';
        });
    }

    function isLiked(trackSrc) { return likedSongs.includes(trackSrc); }
    
    function toggleLike(trackSrc, likeButton) {
        const trackItem = likeButton.closest('.track-item');
        if (isLiked(trackSrc)) {
            likedSongs = likedSongs.filter(src => src !== trackSrc);
            likeButton.innerHTML = '<i class="far fa-heart"></i>';
            if (trackItem) trackItem.classList.remove('liked');
        } else {
            likedSongs.push(trackSrc);
            likeButton.innerHTML = '<i class="fas fa-heart"></i>';
            if (trackItem) trackItem.classList.add('liked');
        }
        localStorage.setItem('likedSongs', JSON.stringify(likedSongs));
    }
    
    function showModal(modal) {
        modalOverlay.classList.remove('hidden');
        modal.classList.remove('hidden');
    }

    function hideModals() {
        modalOverlay.classList.add('hidden');
        document.querySelectorAll('.modal').forEach(m => m.classList.add('hidden'));
    }

    function showContextMenu(e, id) {
        e.preventDefault();
        contextTargetId = id;
        contextMenu.style.top = `${e.pageY}px`;
        contextMenu.style.left = `${e.pageX}px`;
        contextMenu.classList.remove('hidden');
    }

    function hideContextMenu() {
        contextMenu.classList.add('hidden');
    }

    // --- EVENT LISTENERS ---
    playPauseBtn.addEventListener('click', () => { if (audioPlayer.src) isPlaying ? pauseTrack() : playTrack(); });
    nextBtn.addEventListener('click', () => {
        if (!currentPlaylist.length) return;
        if (isShuffle) {
            let randomIndex;
            do { randomIndex = Math.floor(Math.random() * currentPlaylist.length); } 
            while (currentPlaylist.length > 1 && randomIndex === trackIndex);
            trackIndex = randomIndex;
        } else {
            trackIndex = (trackIndex + 1) % currentPlaylist.length;
        }
        loadTrack(trackIndex);
    });
    prevBtn.addEventListener('click', () => {
        if (!currentPlaylist.length) return;
        trackIndex = (trackIndex - 1 + currentPlaylist.length) % currentPlaylist.length;
        loadTrack(trackIndex);
    });
    shuffleBtn.addEventListener('click', () => {
        isShuffle = !isShuffle;
        shuffleBtn.classList.toggle('active', isShuffle);
    });
    audioPlayer.addEventListener('timeupdate', () => {
        const { duration, currentTime } = audioPlayer;
        progressBar.value = (currentTime / duration) * 100 || 0;
        currentTimeEl.textContent = formatTime(currentTime);
        durationEl.textContent = formatTime(duration);
    });
    audioPlayer.addEventListener('ended', () => nextBtn.click());
    audioPlayer.addEventListener('play', () => { isPlaying = true; updateTrackItemUI(); });
    audioPlayer.addEventListener('pause', () => { isPlaying = false; updateTrackItemUI(); });
    
    searchBar.addEventListener('input', () => {
        const searchTerm = searchBar.value.toLowerCase();
        displayTracks(allTracks.filter(track => track.title.toLowerCase().includes(searchTerm)));
    });
    logoutBtn.addEventListener('click', () => {
        localStorage.removeItem('isLoggedIn');
        window.location.href = 'login.html';
    });
    
    document.body.addEventListener('click', e => {
        const link = e.target.closest('[data-link]');
        if (link) {
            e.preventDefault();
            navigateTo(link.getAttribute('href'));
        }
        if (!e.target.closest('.context-menu')) {
            hideContextMenu();
        }
    });
    window.addEventListener('popstate', router);

    mainContent.addEventListener('click', async e => {
        const playBtn = e.target.closest('.track-play-btn');
        const likeBtn = e.target.closest('.like-btn');
        const addBtn = e.target.closest('.add-to-playlist-btn');
        const removeBtn = e.target.closest('.remove-song-btn');

        if (playBtn) {
            const index = parseInt(playBtn.dataset.index);
            if (audioPlayer.src.endsWith(currentPlaylist[index].src) && isPlaying) {
                pauseTrack();
            } else {
                loadTrack(index);
            }
        }
        if (likeBtn) {
            toggleLike(likeBtn.dataset.src, likeBtn);
        }
        if (addBtn) {
            songToAdd = allTracks.find(t => t.src === addBtn.dataset.src) || currentPlaylist.find(t => t.src === addBtn.dataset.src);
            const modalList = document.getElementById('modal-playlist-list');
            modalList.innerHTML = playlists.map(p => `<div class="modal-playlist-item" data-id="${p.id}">${p.name}</div>`).join('') || '<p>No playlists created yet.</p>';
            showModal(addToPlaylistModal);
        }
        if (removeBtn) {
            const { playlistId, src } = removeBtn.dataset;
            await api.delete('playlists_api.php', { action: 'deleteSong', playlistId, songSrc: src });
            const playlist = playlists.find(p => p.id === playlistId);
            if (playlist) playlist.songs = playlist.songs.filter(s => s.src !== src);
            router();
        }
        if(e.target.id === 'create-playlist-btn') {
            playlistForm.reset();
            document.getElementById('playlist-modal-title').textContent = 'New Playlist';
            document.getElementById('playlist-id-input').value = '';
            showModal(playlistModal);
        }
    });

    mainContent.addEventListener('contextmenu', e => {
        const card = e.target.closest('.playlist-card');
        if (card) {
            showContextMenu(e, card.dataset.id);
        }
    });

    mainContent.addEventListener('blur', async e => {
        if (e.target.id === 'playlist-name-heading') {
            const newName = e.target.textContent;
            const id = window.location.pathname.split('/').pop();
            await api.put('playlists_api.php', { id, name: newName });
            const playlist = playlists.find(p => p.id === id);
            if (playlist) playlist.name = newName;
        }
    }, true);
    
    playlistForm.addEventListener('submit', async e => {
        e.preventDefault();
        const name = document.getElementById('playlist-name-input').value;
        const id = document.getElementById('playlist-id-input').value;
        if (id) {
            await api.put('playlists_api.php', { id, name });
        } else {
            await api.post('playlists_api.php', { name });
        }
        hideModals();
        await fetchPlaylists();
        router();
    });

    addToPlaylistModal.addEventListener('click', async e => {
        const item = e.target.closest('.modal-playlist-item');
        if (item) {
            const playlistId = item.dataset.id;
            const result = await api.post('playlists_api.php', { action: 'addSong', playlistId, song: songToAdd });
            const playlist = playlists.find(p => p.id === playlistId);
            if (result.success && playlist) {
                if(!playlist.songs.find(s => s.src === songToAdd.src)) {
                   playlist.songs.push(songToAdd);
                }
            }
            alert(result.message);
            hideModals();
        }
    });

    contextMenu.addEventListener('click', async e => {
        const action = e.target.dataset.action;
        const id = contextTargetId;
        hideContextMenu();
        if (action === 'rename') {
            const playlist = playlists.find(p => p.id === id);
            document.getElementById('playlist-modal-title').textContent = 'Rename Playlist';
            document.getElementById('playlist-id-input').value = id;
            document.getElementById('playlist-name-input').value = playlist.name;
            showModal(playlistModal);
        } else if (action === 'delete') {
            if (confirm('Are you sure you want to delete this playlist?')) {
                await api.delete('playlists_api.php', { id });
                await fetchPlaylists();
                router();
            }
        }
    });

    document.getElementById('cancel-playlist-modal').addEventListener('click', hideModals);
    document.getElementById('cancel-add-song-modal').addEventListener('click', hideModals);
    
    function formatTime(seconds) {
        if (isNaN(seconds)) return '0:00';
        const m = Math.floor(seconds / 60);
        const s = Math.floor(seconds % 60);
        return `${m}:${s < 10 ? '0' : ''}${s}`;
    }

    // --- INITIALIZATION ---
    async function initialize() {
        await fetchAllTracks();
        await fetchPlaylists();
        router();
    }
    initialize();
});