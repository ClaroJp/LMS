document.addEventListener('DOMContentLoaded', () => {
  const usersList = document.getElementById('users-ul');
  const userSearch = document.getElementById('user-search');
  const chatWindow = document.getElementById('chat-window');
  const backToUsersBtn = document.getElementById('back-to-users-btn');
  const chatWithTitle = document.getElementById('chat-with');
  const messagesContainer = document.getElementById('messages');
  const messageInput = document.getElementById('message-input');
  const sendBtn = document.getElementById('send-btn');
  const usersListContainer = document.getElementById('users-list');

  if (typeof username === 'undefined' || !username) {
    alert('Username not set. Please login.');
    return;
  }

  const ONLINE_THRESHOLD_MINUTES = 5;
  let onlineUsernames = new Set();
  let filteredUsers = [];
  let currentChatUser = null;

  // Convert last_activity string to Date, or null if missing
  function lastActivityDate(user) {
    return user.last_activity ? new Date(user.last_activity) : null;
  }

  // Determine if user is considered online based on last_activity + socket online list
  function isUserOnline(user) {
    // Online if socket reports online OR last_activity within threshold
    if (onlineUsernames.has(user.username)) return true;

    if (!user.last_activity) return false;

    const lastAct = lastActivityDate(user);
    if (!lastAct) return false;

    const now = new Date();
    const diffMs = now - lastAct;
    return diffMs <= ONLINE_THRESHOLD_MINUTES * 60 * 1000;
  }

  // Render users with status dots and roles
  function renderUsers(usersToRender) {
    usersList.innerHTML = '';

    if (usersToRender.length === 0) {
      usersList.innerHTML = '<li>No users found.</li>';
      return;
    }

    usersToRender.forEach(user => {
      if (user.username === username) return; // skip self

      const li = document.createElement('li');
      li.classList.add('user');
      li.tabIndex = 0;
      li.dataset.username = user.username;

      const statusDot = document.createElement('span');
      statusDot.classList.add('status-dot');
      statusDot.classList.add(isUserOnline(user) ? 'online' : 'offline');
      li.appendChild(statusDot);

      li.appendChild(document.createTextNode(` ${user.username} (${user.role})`));

      li.addEventListener('click', () => openChat(user.username));
      li.addEventListener('keypress', e => {
        if (e.key === 'Enter') openChat(user.username);
      });

      usersList.appendChild(li);
    });
  }

  // Filter users based on search term
  function filterUsers(term) {
    term = term.toLowerCase();
    filteredUsers = allUsers.filter(user => user.username.toLowerCase().includes(term));
  }

  // Open chat with user, fetch history, show chat window
  async function openChat(user) {
    currentChatUser = user;
    chatWithTitle.textContent = `Chat with ${user}`;
    chatWindow.style.display = 'flex';
    usersListContainer.style.display = 'none';
    messageInput.value = '';
    messagesContainer.innerHTML = '';

    try {
      const res = await fetch(`fetch_messages.php?partner=${encodeURIComponent(user)}`);
      if (!res.ok) throw new Error('Failed to fetch chat history');
      const history = await res.json();
      history.forEach(msg => {
        addMessageToUI({ text: msg.message, sent: msg.sent });
      });
      scrollToBottom();
    } catch (err) {
      console.error(err);
    }
  }

  // Back to user list
  backToUsersBtn.addEventListener('click', () => {
    chatWindow.style.display = 'none';
    usersListContainer.style.display = 'flex';
    currentChatUser = null;
  });

  // Send message via socket and backend
  sendBtn.addEventListener('click', sendMessage);
  messageInput.addEventListener('keypress', e => {
    if (e.key === 'Enter') sendMessage();
  });

  async function sendMessage() {
    const text = messageInput.value.trim();
    if (!text || !currentChatUser) return;

    addMessageToUI({ text, sent: true });
    messageInput.value = '';
    scrollToBottom();

    socket.emit('private_message', {
      to: currentChatUser,
      from: username,
      text
    });

    try {
      const res = await fetch('send_message.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ receiver: currentChatUser, message: text })
      });
      const data = await res.json();
      if (!data.success) {
        alert("Message failed to save: " + (data.error || "Unknown error"));
      }
    } catch (err) {
      console.error("Failed to send message via PHP:", err);
    }
  }

  // Add message to chat UI
  function addMessageToUI(message) {
    const div = document.createElement('div');
    div.classList.add('message', message.sent ? 'sent' : 'received');
    div.textContent = message.text;
    messagesContainer.appendChild(div);
    scrollToBottom();
  }

  // Scroll chat to bottom
  function scrollToBottom() {
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
  }

  // Search input event
  userSearch.addEventListener('input', () => {
    filterUsers(userSearch.value);
    renderUsers(filteredUsers);
  });

  // Socket.IO connection
  const socket = io('http://localhost:3000');

  socket.on('connect', () => {
    socket.emit('join', { username });
  });

  socket.on('users', (serverOnlineUsers) => {
    onlineUsernames = new Set(serverOnlineUsers);
    // On each update, re-render filtered list with updated online statuses
    filterUsers(userSearch.value);
    renderUsers(filteredUsers);
  });

  socket.on('private_message', (msg) => {
    if (
      (msg.from === currentChatUser && msg.to === username) ||
      (msg.to === currentChatUser && msg.from === username)
    ) {
      addMessageToUI({
        text: msg.text,
        sent: msg.from === username
      });
    }
  });

  // Initial render with full list
  filterUsers('');
  renderUsers(filteredUsers);
});
