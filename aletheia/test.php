<?php
/**
 * organizational_notices.php
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        body { background: #0c0c0c; color: #d1d1d1; font-family: 'Inter', sans-serif; padding: 50px; }
        .container { max-width: 800px; margin: auto; }
        .org-label { color: #555; font-size: 0.75rem; margin-top: 40px; margin-bottom: 10px; display: block; font-weight: bold; text-transform: uppercase; letter-spacing: 2px; }

        /* Base Notice Style */
        .notice { width: 100%; box-sizing: border-box; margin-bottom: 25px; position: relative; }

        /* --- GOVERNMENT & CORPORATE --- */

        /* 1. INTERPOL - Global Policing (Clean, blue, authoritative) */
        .interpol {
            background: #fff; color: #003366; padding: 15px 25px;
            border-top: 6px solid #003366; border-bottom: 2px solid #003366;
            font-family: 'Helvetica Neue', Arial, sans-serif;
        }
        .interpol::before { content: "INTERPOL NOTICE // INTERNATIONAL ALERT"; display: block; font-size: 0.65rem; font-weight: 900; margin-bottom: 5px; opacity: 0.8; }

        /* 2. THE PENTAGON (Tactical Grid) */
        .pentagon {
            background: #1a1c1e; color: #c5c6c7; border: 1px solid #45474a;
            padding: 20px; clip-path: polygon(0 0, 100% 0, 95% 100%, 5% 100%);
        }
        .pentagon::after { content: "DOD_SEC_PROTOCOL_4"; position: absolute; right: 40px; bottom: 5px; font-size: 0.6rem; color: #666; }

        /* 3. WEYLAND-YUCHI CORP (Sci-Fi Corporate - Sleek, Gold/Black) */
        .weyland {
            background: #000; color: #d4af37; border-left: 2px solid #d4af37;
            padding: 20px 40px; font-family: 'Futura', sans-serif; letter-spacing: 1px;
        }
        .weyland::before { content: "BUILDING BETTER WORLDS"; position: absolute; top: 5px; right: 20px; font-size: 0.5rem; opacity: 0.5; }

        /* --- SCIENTIFIC & ANOMALOUS --- */

        /* 4. CERN (High-Energy Physics - Blueprint style) */
        .cern {
            background: #004a99; color: #fff; border: 1px solid rgba(255,255,255,0.3);
            background-image: linear-gradient(rgba(255,255,255,0.05) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.05) 1px, transparent 1px);
            background-size: 15px 15px; padding: 25px;
        }
        .cern::before { content: "PARTICLE COLLIDER STATUS: ACTIVE"; display: block; font-size: 0.7rem; font-family: monospace; border-bottom: 1px solid #fff; margin-bottom: 10px; }

        /* 5. SCP FOUNDATION (Containment - Dark Styled) */
        .scp-dark {
            background: #050505; color: #fff; border: 1px solid #444; padding: 25px;
            box-shadow: inset 0 0 15px rgba(255, 0, 0, 0.4);
        }
        .scp-dark::before { content: "⚠ COGNITOHAZARD WARNING"; display: block; color: #ff0000; font-weight: bold; text-align: center; margin-bottom: 10px; font-size: 0.9rem; }

        /* 6. GLOBAL OCCULT COALITION (GOC) - Paramilitary Clinical */
        .goc {
            background: #1b263b; color: #e0e1dd; border-left: 10px solid #778da9;
            padding: 15px 30px; font-family: 'Courier New', monospace;
        }

        /* --- DARK WEB / DECENTRALIZED NODES (Non-Stereotypical) --- */

        /* 7. ONION NODE - (Tor-style browser/Minimalist utilitarian) */
        .onion-node {
            background: #000; color: #a6e3a1; border: 1px solid #313244;
            padding: 15px; font-family: monospace; border-radius: 4px;
        }
        .onion-node::before { content: "[circuit_established] :: relay_312"; display: block; color: #89b4fa; font-size: 0.7rem; margin-bottom: 8px; }

        /* 8. P2P ENCRYPTED MESH (Brutalist text-only) */
        .mesh-net {
            border: 2px solid #fff; padding: 20px;
            background: transparent; color: #fff; font-weight: bold;
            text-transform: uppercase; font-size: 1.1rem;
        }
        .mesh-net::after { content: "SIGNED_GPG_KEY_7701"; display: block; font-size: 0.6rem; margin-top: 10px; border-top: 1px solid #fff; padding-top: 5px; }

        /* 9. THE BLACK MARKET (Functional, clean, high-contrast) */
        .market {
            background: #111; color: #ff0055; border: 1px solid #ff0055;
            padding: 0; display: flex;
        }
        .market-side { background: #ff0055; width: 40px; display: flex; align-items: center; justify-content: center; color: #000; font-weight: 900; }
        .market-body { padding: 15px; flex: 1; }

        /* 10. THE ARCHIVE (Historical/leaked document look) */
        .archive {
            background: #fdf6e3; color: #586e75; border: 1px solid #93a1a1;
            padding: 30px; font-family: 'Georgia', serif; box-shadow: 2px 2px 10px rgba(0,0,0,0.5);
        }
        .archive::before { content: "DECLASSIFIED / LEAKED"; display: block; font-size: 0.6rem; color: #dc322f; text-decoration: underline; margin-bottom: 10px; }

        /* 11. CRYPTO-CELL (Hardware Wallet UI style) */
        .crypto {
            background: #121212; color: #00ff00; border: 1px solid #333;
            border-radius: 12px; font-family: 'JetBrains Mono', monospace; padding: 15px;
        }
        .crypto::before { content: "● ENCRYPTED_CHANNEL_01"; color: #00ff00; font-size: 0.7rem; display: block; margin-bottom: 5px; }

        /* 12. RED ROOM EMULATION (Stark, aggressive red text on black) */
        .void-comm {
            background: #000; color: #f00; border: 1px solid #f00;
            text-align: center; padding: 20px; font-size: 1.2rem;
            letter-spacing: 5px; font-family: 'Impact', sans-serif;
        }

        /* 13. NEURALINK / BIOTECH (Clinical, white, rounded) */
        .biotech {
            background: #f0f2f5; color: #1c1e21; border-radius: 20px;
            border: 1px solid #ccd0d5; padding: 20px; font-size: 0.9rem;
        }
        .biotech::before { content: "BIO-SENSOR SYNC: 98%"; font-size: 0.6rem; font-weight: bold; color: #1877f2; }

        /* 14. PHANTOM NODE (Semi-transparent, blurred) */
        .phantom {
            background: rgba(255,255,255,0.05); backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1); padding: 20px;
            color: #fff; text-shadow: 0 0 10px rgba(255,255,255,0.5);
        }

        /* 15. THE SNIFFER (Packet capture style) */
        .sniffer {
            background: #111; color: #666; font-family: monospace; font-size: 0.75rem;
            padding: 15px; border-left: 3px solid #666;
        }
        .sniffer b { color: #fff; }
    </style>
</head>
<body>

<div class="container">
    <span class="org-label">Interpol</span>
    <div class="notice interpol">A Red Notice has been issued for user ID #9928. All nodes must log traffic for this user immediately.</div>

    <span class="org-label">The Pentagon (DoD)</span>
    <div class="notice pentagon">Classified transmission received. Decryption in progress. Unauthorized viewing is a felony under Title 18.</div>

    <span class="org-label">Weyland-Yutani Corp</span>
    <div class="notice weyland">Security personnel are reminded that corporate property is to be handled with extreme care. Crew expendable.</div>

    <span class="org-label">CERN</span>
    <div class="notice cern">Anomalous readings detected in the Large Hadron Collider. All non-essential staff must exit the containment zone.</div>

    <span class="org-label">SCP Foundation (Dark Mode)</span>
    <div class="notice scp-dark">MEMETIC HAZARD: Do not read the following text if you have not received Level 4 inoculation.</div>

    <span class="org-label">Global Occult Coalition</span>
    <div class="notice goc">KTE-2032-Ex-Machina has been identified. Strike Team 001 is inbound for neutralization.</div>

    <span class="org-label">Tor / Onion Node</span>
    <div class="notice onion-node">Welcome to node 127.0.0.1. Exit traffic is currently being rerouted through Germany. Keep your headers clean.</div>

    <span class="org-label">Encrypted Mesh Net</span>
    <div class="notice mesh-net">Network heartbeat detected. 4 peers online. No central server. No logs. No masters.</div>

    <span class="org-label">The Black Market</span>
    <div class="notice market">
        <div class="market-side">!</div>
        <div class="market-body">Escrow service is mandatory for all transactions over 0.5 BTC. Scammers will be blacklisted across the mesh.</div>
    </div>

    <span class="org-label">The Archive (Leak)</span>
    <div class="notice archive">Internal Memo: The experimental server farm in the Arctic has been compromised. Do not attempt to reconnect.</div>

    <span class="org-label">Hardware Crypto Node</span>
    <div class="notice crypto">Transaction ID: 0x4f2... verified. Block 882,921 added to ledger. Funds released to cold storage.</div>

    <span class="org-label">The Void / Red Room</span>
    <div class="notice void-comm">YOU ARE NOT ALONE IN THIS CHANNEL</div>

    <span class="org-label">Neuralink / Biotech</span>
    <div class="notice biotech">Your cognitive upload is 42% complete. Please remain still to avoid synapse corruption.</div>

    <span class="org-label">Phantom Node</span>
    <div class="notice phantom">This message will dissolve into the network buffer in 60 seconds. Make every second count.</div>

    <span class="org-label">Network Sniffer</span>
    <div class="notice sniffer">[INBOUND] <b>192.168.1.1</b> -> <b>10.0.0.5</b> [TCP] <b>Warning:</b> SYN Flood detected. Filtering active.</div>
</div>

</body>
</html>