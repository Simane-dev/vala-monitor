<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>VALA — Monitor</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="container">
  <nav class="nav">
    <div class="nav-left"><div class="dot"></div> VALA MONITOR</div>
    <div class="nav-right">Système de vérification</div>
  </nav>

  <main class="main">
    <div class="left">
      <h1>Vérifier<br>la disponibilité<br>d'un domaine.</h1>
      <p>Vérification intégrale. Score de fiabilité sur 100. Résultat instantané.</p>
      
      <div class="search-wrap">
        <div class="label">DOMAINE À VÉRIFIER</div>
        <div class="search-box">
          <span class="prefix">https://</span>
          <input type="text" id="domainInput" placeholder="exemple.com" autocomplete="off">
          <button id="checkBtn">→</button>
        </div>
        <div class="hint">Entrée pour lancer. Pas besoin de www.</div>
      </div>
    </div>

    <div class="right" id="resultArea">
      <div class="empty-state">
        <div class="empty-line"></div>
        <div class="empty-line"></div>
        <div class="empty-line"></div>
        <div class="empty-line"></div>
        <div class="empty-line"></div>
        <div class="empty-line"></div>
        <div class="empty-line"></div>
        <div class="empty-line short"></div>
        <div class="empty-text">En attente d'un domaine...</div>
      </div>
    </div>
  </main>

  <section id="detailsSection" style="display:none"></section>

  <footer class="footer">
    <span>© 2026 VALA-MONITOR</span>
    <span>SANS TRAQUEURS</span>
  </footer>
</div>

<script src="assets/script.js"></script>
</body>
</html>