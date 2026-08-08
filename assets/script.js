async function runCheck() {
    const input = document.getElementById('domainInput');
    const btn = document.getElementById('checkBtn');
    const resultArea = document.getElementById('resultArea');
    const detailsSection = document.getElementById('detailsSection');
    
    // 1. تنظيف الـ Input من أي رموش أو بروتوكولات
    let domain = input.value.trim().replace(/^https?:\/\//, '').replace(/^www\./, '').replace(/\/.*/, '');

    if (!domain) { 
        input.style.borderColor = "#ff3b30"; 
        return; 
    }
    input.style.borderColor = "";

    // حالة التحميل
    btn.innerHTML = '...';
    btn.disabled = true;
    resultArea.innerHTML = `<div style="padding:40px;font-family:serif;text-align:center;">Vérification en cours de <strong>${domain}</strong>...</div>`;
    detailsSection.style.display = 'none';

    // CHECK CONNEXION
    if (!navigator.onLine) {
        throw new Error("NETWORK_OFFLINE");
    }

    try {
        // الاتصال المباشر بالـ API الحقيقي (Real-time Scan)
        const res = await fetch(`api/check.php?domain=${encodeURIComponent(domain)}`);
        
        if (!res.ok) {
            throw new Error(`Erreur serveur: ${res.status}`);
        }

        const data = await res.json();

        if (data.status === 'error') {
            throw new Error(data.message || "Impossible de vérifier ce domaine.");
        }

        // 2. استخراج البيانات الحقيقية فقط من الـ API
        const scoreVal = data.score?.score ?? 0;
        
        let grade = 'F';
        if (scoreVal >= 90) grade = 'A';
        else if (scoreVal >= 70) grade = 'B';
        else if (scoreVal >= 50) grade = 'C';

        const httpCode = data.details?.http?.status_code ?? 0;
        const responseTime = data.details?.http?.response_time ?? 0;
        const sslDays = data.details?.ssl?.days_left ?? 0;
        const mxOk = data.details?.dns?.mx_valid ?? false;
        const isAvailable = data.available ?? false;

        // 3. عرض النتيجة الحقيقية
        resultArea.innerHTML = `
            <div class="result-summary">
                <div class="result-label">RÉSULTAT POUR</div>
                <div class="domain-title">${data.domain || domain}</div>

                <div class="metrics-row">
                    <div class="metric-box">
                        <div class="metric-label">FIABILITÉ</div>
                        <div class="score-number">${scoreVal}</div>
                    </div>
                    <div class="metric-box">
                        <div class="metric-label">GRADE</div>
                        <div class="grade-badge ${grade === 'F' ? 'grade-f' : ''}">${grade}</div>
                        <div class="domain-status">${isAvailable ? 'Disponible à l\'achat' : 'Domaine pris'}</div>
                    </div>
                </div>

                <div class="summary-grid">
                    <div><span class="muted">HTTP CODE:</span> <strong>${httpCode ? httpCode + ' OK' : 'Non joignable'}</strong></div>
                    <div><span class="muted">SSL VALIDITÉ:</span> <strong>${sslDays} j</strong></div>
                    <div><span class="muted">MX CONFIGURÉ:</span> <strong>${mxOk ? 'Oui' : 'Non'}</strong></div>
                    <div><span class="muted">TEMPS RÉPONSE:</span> <strong>${responseTime} ms</strong></div>
                    <div><span class="muted">DNS STATUS:</span> <strong>${httpCode ? 'Résolu' : 'Échec DNS'}</strong></div>
                    <div><span class="muted">DISPONIBILITÉ:</span> <strong>${isAvailable ? 'Oui' : 'Non'}</strong></div>
                </div>
            </div>
        `;

        // 4. التفاصيل التقنية والـ Raw JSON الحقيقي
        detailsSection.style.display = 'block';
        detailsSection.innerHTML = `
            <h2>Détails techniques</h2>
            <div class="details-grid">
                <div class="detail-card">
                    <span>HTTP CODE</span>
                    <strong>${httpCode ? httpCode : 'N/A'}</strong>
                    <p>${httpCode === 200 ? 'Requête réussie' : 'Serveur indisponible'}</p>
                </div>
                <div class="detail-card">
                    <span>SSL VALIDITÉ</span>
                    <strong>${sslDays} j</strong>
                    <p>${sslDays > 0 ? 'Certificat valide' : 'Aucun SSL / Expiré'}</p>
                </div>
                <div class="detail-card">
                    <span>MX CONFIGURÉ</span>
                    <strong>${mxOk ? 'Oui' : 'Non'}</strong>
                    <p>${mxOk ? 'Serveur mail détecté' : 'Aucun serveur mail'}</p>
                </div>
                <div class="detail-card">
                    <span>TEMPS RÉPONSE</span>
                    <strong>${responseTime} ms</strong>
                    <p>Latence serveur</p>
                </div>
                <div class="detail-card">
                    <span>DNS STATUS</span>
                    <strong>${httpCode ? 'Résolu' : 'Inconnu'}</strong>
                    <p>${domain}</p>
                </div>
                <div class="detail-card">
                    <span>DISPONIBILITÉ</span>
                    <strong>${isAvailable ? 'Disponible' : 'Pris'}</strong>
                    <p>${isAvailable ? 'À vendre' : 'Enregistré'}</p>
                </div>
            </div>
            <div class="details-json">
                <div class="json-header">
                    <span>Raw Response (JSON)</span>
                    <button class="copy-btn" onclick="navigator.clipboard.writeText(document.getElementById('json-pre').innerText)">COPY</button>
                </div>
                <pre id="json-pre">${JSON.stringify(data, null, 2)}</pre>
            </div>
        `;

        } catch (error) {
        const isOffline = error.message === "NETWORK_OFFLINE" || error.name === "AbortError" || error.message.includes("Failed to fetch") || !navigator.onLine;

        if (isOffline) {
            resultArea.innerHTML = `
                <div style="padding:40px; text-align:center; border:1.5px solid #111; background:white">
                    <div style="font-size:40px;margin-bottom:12px">📡</div>
                    <div style="font-family:serif;font-size:22px;font-weight:800">Pas de connexion internet</div>
                    <div style="font-size:11px;opacity:.6;margin-top:8px">Vérifiez votre Wi-Fi et réessayez</div>
                    <button onclick="runCheck()" style="margin-top:16px;padding:8px 16px;background:#111;color:white;border:none;cursor:pointer">RÉESSAYER →</button>
                </div>
            `;
        } else {
            resultArea.innerHTML = `
                <div style="padding:40px; text-align:center;">
                    <div style="color:#ff3b30; font-weight:bold; font-size:16px; margin-bottom:8px;">
                        ❌ Impossible de vérifier ce domaine
                    </div>
                    <div style="font-size:12px; color:#666;">
                        ${error.message || 'Le domaine est invalide ou le serveur ne répond pas.'}
                    </div>
                </div>
            `;
        }
    } finally {
        btn.innerHTML = '→';
        btn.disabled = false;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('checkBtn');
    const input = document.getElementById('domainInput');
    if (!btn || !input) return;
    
    btn.addEventListener('click', (e) => { e.preventDefault(); runCheck(); });
    input.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); runCheck(); } });
    input.addEventListener('input', () => { input.style.borderColor = ""; });
});