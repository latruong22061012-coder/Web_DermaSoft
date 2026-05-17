/* ──────────────────────────────────────────────────────────
   DarmaBot — Chatbot widget (read-only, role bệnh nhân)
   ────────────────────────────────────────────────────────── */
(function () {
  "use strict";

  var root = document.getElementById("dermasoft-chatbot");
  if (!root) return;

  var isLoggedIn = root.dataset.loggedIn === "1";
  var isPatient = root.dataset.role === "4";
  var userId = root.dataset.userId || "0";
  var userName = root.dataset.name || "bạn";
  var baseUrl = root.dataset.baseUrl || "/";
  var loginUrl = root.dataset.loginUrl || baseUrl + "index.php?route=login";
  var apiSend = baseUrl + "index.php?route=api/chatbot/send";
  var apiConfig = baseUrl + "index.php?route=api/chatbot/config";

  var storageKey = "dermasoft_chat_" + userId;
  var MAX_HISTORY = 20;

  // ── Build DOM ─────────────────────────────────────────────
  var bubble = document.createElement("button");
  bubble.type = "button";
  bubble.className = "dbot-bubble";
  bubble.setAttribute("aria-label", "Mở trợ lý AI");
  bubble.setAttribute("aria-expanded", "false");
  bubble.innerHTML =
    '<i class="bi bi-chat-dots-fill"></i><span class="dbot-dot"></span>';

  var panel = document.createElement("div");
  panel.className = "dbot-panel";
  panel.setAttribute("role", "dialog");
  panel.setAttribute("aria-label", "Trợ lý AI");

  document.body.appendChild(bubble);
  document.body.appendChild(panel);

  bubble.addEventListener("click", function () {
    var open = panel.classList.toggle("dbot-open");
    bubble.setAttribute("aria-expanded", open ? "true" : "false");
    if (open && !panel.dataset.built) {
      buildPanel();
      panel.dataset.built = "1";
    }
  });

  // ── Login required ────────────────────────────────────────
  function buildPanel() {
    if (!isLoggedIn || !isPatient) {
      panel.innerHTML =
        "" +
        '<div class="dbot-header">' +
        '  <div class="dbot-avatar"><i class="bi bi-robot"></i></div>' +
        '  <div><div class="dbot-title">DremaBot</div><div class="dbot-sub">Trợ lý AI phòng khám</div></div>' +
        '  <div class="dbot-actions"><button type="button" data-act="close" aria-label="Đóng"><i class="bi bi-x-lg"></i></button></div>' +
        "</div>" +
        '<div class="dbot-login-cta">' +
        '  <i class="bi bi-person-lock"></i>' +
        "  <div>" +
        (isLoggedIn
          ? "Trợ lý AI chỉ phục vụ tài khoản bệnh nhân."
          : "Vui lòng đăng nhập tài khoản bệnh nhân<br>để trò chuyện cùng trợ lý AI.") +
        "</div>" +
        (isLoggedIn
          ? ""
          : '  <a href="' + escapeAttr(loginUrl) + '">Đăng nhập</a>') +
        "</div>";
      panel
        .querySelector('[data-act="close"]')
        .addEventListener("click", closePanel);
      return;
    }

    panel.innerHTML =
      "" +
      '<div class="dbot-header">' +
      '  <div class="dbot-avatar"><i class="bi bi-robot"></i></div>' +
      '  <div><div class="dbot-title">DermaBot</div><div class="dbot-sub">Chào ' +
      escapeHtml(userName) +
      "!</div></div>" +
      '  <div class="dbot-actions">' +
      '    <button type="button" data-act="reset" title="Bắt đầu lại"><i class="bi bi-arrow-clockwise"></i></button>' +
      '    <button type="button" data-act="close" title="Đóng"><i class="bi bi-x-lg"></i></button>' +
      "  </div>" +
      "</div>" +
      '<div class="dbot-body" id="dbot-body"></div>' +
      '<div class="dbot-suggestions" id="dbot-suggestions"></div>' +
      '<form class="dbot-input" id="dbot-form" autocomplete="off">' +
      '  <textarea id="dbot-input" rows="1" placeholder="Nhập câu hỏi…" maxlength="1000"></textarea>' +
      '  <button type="submit" id="dbot-send" title="Gửi"><i class="bi bi-send-fill"></i></button>' +
      "</form>";

    panel
      .querySelector('[data-act="close"]')
      .addEventListener("click", closePanel);
    panel
      .querySelector('[data-act="reset"]')
      .addEventListener("click", resetChat);
    panel.querySelector("#dbot-form").addEventListener("submit", onSubmit);

    var ta = panel.querySelector("#dbot-input");
    ta.addEventListener("keydown", function (e) {
      if (e.key === "Enter" && !e.shiftKey) {
        e.preventDefault();
        panel.querySelector("#dbot-form").requestSubmit();
      }
    });
    ta.addEventListener("input", function () {
      ta.style.height = "auto";
      ta.style.height = Math.min(100, ta.scrollHeight) + "px";
    });

    loadHistory();
    loadSuggestions();
  }

  function closePanel() {
    panel.classList.remove("dbot-open");
    bubble.setAttribute("aria-expanded", "false");
  }

  // ── History (localStorage) ────────────────────────────────
  function readHistory() {
    try {
      var raw = localStorage.getItem(storageKey);
      if (!raw) return [];
      var arr = JSON.parse(raw);
      return Array.isArray(arr) ? arr : [];
    } catch (e) {
      return [];
    }
  }
  function writeHistory(arr) {
    try {
      var trimmed = arr.slice(-MAX_HISTORY * 2); // user+bot
      localStorage.setItem(storageKey, JSON.stringify(trimmed));
    } catch (e) {
      /* quota */
    }
  }
  function loadHistory() {
    var history = readHistory();
    var body = panel.querySelector("#dbot-body");
    if (!history.length) {
      appendSystem(
        "Xin chào " +
          userName +
          "! Tôi là DarmaBot, có thể giúp bạn tra cứu dịch vụ, bác sĩ, giờ làm việc và lịch hẹn của bạn.",
      );
      return;
    }
    // Đã có cuộc trò chuyện trước → ẩn câu hỏi gợi ý
    var sbox = panel.querySelector("#dbot-suggestions");
    if (sbox) sbox.classList.add("dbot-suggestions-hidden");
    history.forEach(function (m) {
      appendMessage(m.role, m.text, /*persist*/ false);
    });
  }
  function resetChat() {
    if (!confirm("Bắt đầu cuộc trò chuyện mới? Lịch sử hiện tại sẽ bị xóa."))
      return;
    localStorage.removeItem(storageKey);
    panel.querySelector("#dbot-body").innerHTML = "";
    // Hiện lại ô gợi ý
    var box = panel.querySelector("#dbot-suggestions");
    if (box) box.classList.remove("dbot-suggestions-hidden");
    appendSystem("Đã làm mới cuộc trò chuyện.");
  }

  // ── Suggestions ───────────────────────────────────────────
  function loadSuggestions() {
    fetch(apiConfig, { credentials: "same-origin" })
      .then(function (r) {
        return r.json();
      })
      .then(function (j) {
        if (
          !j ||
          j.status !== 200 ||
          !j.data ||
          !Array.isArray(j.data.suggestedQuestions)
        )
          return;
        var box = panel.querySelector("#dbot-suggestions");
        if (!box) return;
        box.innerHTML = "";
        j.data.suggestedQuestions.slice(0, 4).forEach(function (q) {
          var b = document.createElement("button");
          b.type = "button";
          b.textContent = q;
          b.addEventListener("click", function () {
            sendMessage(q);
          });
          box.appendChild(b);
        });
      })
      .catch(function () {
        /* silent */
      });
  }

  // ── Send ──────────────────────────────────────────────────
  function onSubmit(e) {
    e.preventDefault();
    var ta = panel.querySelector("#dbot-input");
    var msg = (ta.value || "").trim();
    if (!msg) return;
    ta.value = "";
    ta.style.height = "auto";
    // User gõ câu hỏi tự nhập → ẩn ô gợi ý
    hideSuggestions();
    sendMessage(msg);
  }

  function hideSuggestions() {
    var box = panel.querySelector("#dbot-suggestions");
    if (box && !box.classList.contains("dbot-suggestions-hidden")) {
      box.classList.add("dbot-suggestions-hidden");
    }
  }

  function sendMessage(msg) {
    if (msg.length > 1000) {
      appendError("Câu hỏi quá dài (tối đa 1000 ký tự).");
      return;
    }
    appendMessage("user", msg, true);
    var sendBtn = panel.querySelector("#dbot-send");
    sendBtn.disabled = true;
    showTyping(true);

    var csrf = getCsrf();
    var history = readHistory().slice(0, -1); // bỏ tin user vừa append

    fetch(apiSend, {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-Token": csrf,
      },
      body: JSON.stringify({ message: msg, messages: history }),
    })
      .then(function (r) {
        return r.json().then(function (j) {
          return { http: r.status, body: j };
        });
      })
      .then(function (res) {
        showTyping(false);
        sendBtn.disabled = false;
        if (res.http === 401) {
          appendError("Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.");
          return;
        }
        if (res.http === 403) {
          appendError(
            res.body && res.body.message
              ? res.body.message
              : "Bạn không có quyền sử dụng.",
          );
          return;
        }
        if (res.http === 429) {
          appendError(
            res.body && res.body.message
              ? res.body.message
              : "Quá nhiều yêu cầu.",
          );
          return;
        }
        if (!res.body || res.body.status !== 200) {
          appendError(
            (res.body && res.body.message) ||
              "Trợ lý AI tạm gián đoạn, vui lòng thử lại.",
          );
          return;
        }
        var reply =
          res.body.data && res.body.data.reply ? res.body.data.reply : "...";
        appendMessage("model", reply, true);
      })
      .catch(function () {
        showTyping(false);
        sendBtn.disabled = false;
        appendError("Không kết nối được. Vui lòng thử lại.");
      });
  }

  function getCsrf() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute("content") || "" : "";
  }

  // ── Rendering ─────────────────────────────────────────────
  function appendMessage(role, text, persist) {
    var body = panel.querySelector("#dbot-body");
    if (!body) return;
    var div = document.createElement("div");
    div.className = "dbot-msg " + (role === "user" ? "user" : "bot");
    if (role === "user") {
      div.textContent = text;
    } else {
      div.innerHTML = renderMarkdown(text);
    }
    body.appendChild(div);
    body.scrollTop = body.scrollHeight;
    if (persist) {
      var h = readHistory();
      h.push({ role: role === "user" ? "user" : "model", text: text });
      writeHistory(h);
    }
  }
  function appendSystem(text) {
    var body = panel.querySelector("#dbot-body");
    if (!body) return;
    var div = document.createElement("div");
    div.className = "dbot-msg system";
    div.textContent = text;
    body.appendChild(div);
    body.scrollTop = body.scrollHeight;
  }
  function appendError(text) {
    var body = panel.querySelector("#dbot-body");
    if (!body) return;
    var div = document.createElement("div");
    div.className = "dbot-msg bot error";
    div.textContent = text;
    body.appendChild(div);
    body.scrollTop = body.scrollHeight;
  }
  function showTyping(on) {
    var body = panel.querySelector("#dbot-body");
    if (!body) return;
    var existing = body.querySelector(".dbot-typing");
    if (on) {
      if (existing) return;
      var t = document.createElement("div");
      t.className = "dbot-typing";
      t.innerHTML = "<span></span><span></span><span></span>";
      body.appendChild(t);
      body.scrollTop = body.scrollHeight;
    } else if (existing) {
      existing.remove();
    }
  }

  // ── Markdown rendering (safe) ─────────────────────────────
  // Escape trước, rồi áp regex cho **bold**, *italic*, list, link.
  function renderMarkdown(src) {
    var safe = escapeHtml(src || "");
    // Code inline `xxx`
    safe = safe.replace(/`([^`]+)`/g, "<code>$1</code>");
    // Bold **xxx**
    safe = safe.replace(/\*\*([^*]+)\*\*/g, "<strong>$1</strong>");
    // Italic *xxx* (đơn giản — sau xử lý bold)
    safe = safe.replace(
      /(^|[\s(])\*([^*\n]+)\*(?=[\s).,!?]|$)/g,
      "$1<em>$2</em>",
    );
    // Links http(s) → anchor (URL đã được escape, tiếp tục an toàn)
    safe = safe.replace(
      /(https?:\/\/[^\s<]+)/g,
      '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>',
    );
    // Bullet lists: dòng bắt đầu bằng - hoặc *
    var lines = safe.split("\n");
    var html = "",
      inList = false;
    for (var i = 0; i < lines.length; i++) {
      var line = lines[i];
      var m = /^\s*[-*]\s+(.*)$/.exec(line);
      if (m) {
        if (!inList) {
          html += "<ul>";
          inList = true;
        }
        html += "<li>" + m[1] + "</li>";
      } else {
        if (inList) {
          html += "</ul>";
          inList = false;
        }
        if (line.trim() === "") {
          html += "<br>";
        } else {
          html += line + "<br>";
        }
      }
    }
    if (inList) html += "</ul>";
    return html;
  }

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }
  function escapeAttr(s) {
    return escapeHtml(s);
  }
})();
