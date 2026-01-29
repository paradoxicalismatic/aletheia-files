<?php
header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];

// A simple way to identify the user. In a real app, this would come from a secure session.
$user = 'default-user'; 

$baseUserPath = __DIR__ . '/users/' . $user;
$playlistsPath = $baseUserPath . '/playlists/';

// Ensure the user's playlists directory exists.
if (!is_dir($playlistsPath)) {
    mkdir($playlistsPath, 0777, true);
}

// --- Main Logic Router ---
switch ($method) {
    case 'GET':
        // Get all playlists for the user.
        $files = scandir($playlistsPath);
        $playlists = [];
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
                $content = json_decode(file_get_contents($playlistsPath . $file), true);
                $playlists[] = $content;
            }
        }
        // Sort playlists by name
        usort($playlists, function($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });
        echo json_encode($playlists);
        break;

    case 'POST':
        // Create a new playlist or add a song to an existing one.
        $data = json_decode(file_get_contents('php://input'), true);

        if (isset($data['action']) && $data['action'] === 'addSong') {
            // Add a song to a playlist
            $playlistId = basename($data['playlistId']); // Sanitize input
            $song = $data['song'];
            $filePath = $playlistsPath . $playlistId . '.json';

            if (file_exists($filePath)) {
                $playlist = json_decode(file_get_contents($filePath), true);
                
                $songExists = false;
                foreach($playlist['songs'] as $existingSong) {
                    if ($existingSong['src'] === $song['src']) {
                        $songExists = true;
                        break;
                    }
                }

                if (!$songExists) {
                    $playlist['songs'][] = $song;
                    file_put_contents($filePath, json_encode($playlist, JSON_PRETTY_PRINT));
                    echo json_encode(['success' => true, 'message' => 'Song added.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Song already in playlist.']);
                }
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Playlist not found.']);
            }
        } else {
            // Create a new playlist
            $playlistName = $data['name'];
            $playlistId = 'pl_' . uniqid();
            $newPlaylist = [
                'id' => $playlistId,
                'name' => $playlistName,
                'songs' => []
            ];
            file_put_contents($playlistsPath . $playlistId . '.json', json_encode($newPlaylist, JSON_PRETTY_PRINT));
            echo json_encode($newPlaylist);
        }
        break;
        
    case 'PUT':
        // Rename a playlist
        $data = json_decode(file_get_contents('php://input'), true);
        $playlistId = basename($data['id']);
        $newName = $data['name'];
        $filePath = $playlistsPath . $playlistId . '.json';

        if(file_exists($filePath)){
            $playlist = json_decode(file_get_contents($filePath), true);
            $playlist['name'] = $newName;
            file_put_contents($filePath, json_encode($playlist, JSON_PRETTY_PRINT));
            echo json_encode($playlist);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Playlist not found.']);
        }
        break;

    case 'DELETE':
        // Delete a playlist or a song from a playlist.
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (isset($data['action']) && $data['action'] === 'deleteSong') {
            // Delete a song from a playlist
            $playlistId = basename($data['playlistId']);
            $songSrc = $data['songSrc'];
            $filePath = $playlistsPath . $playlistId . '.json';

            if (file_exists($filePath)) {
                $playlist = json_decode(file_get_contents($filePath), true);
                $playlist['songs'] = array_values(array_filter($playlist['songs'], function($s) use ($songSrc) {
                    return $s['src'] !== $songSrc;
                }));
                file_put_contents($filePath, json_encode($playlist, JSON_PRETTY_PRINT));
                echo json_encode(['success' => true, 'message' => 'Song removed.']);
            } else {
                 http_response_code(404);
                echo json_encode(['error' => 'Playlist not found.']);
            }

        } else {
            // Delete an entire playlist
            $playlistId = basename($data['id']);
            $filePath = $playlistsPath . $playlistId . '.json';

            if (file_exists($filePath)) {
                unlink($filePath);
                echo json_encode(['success' => true, 'message' => 'Playlist deleted.']);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Playlist not found.']);
            }
        }
        break;
}
?>