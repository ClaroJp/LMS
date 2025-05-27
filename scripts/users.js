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

  let users = []; // all users except self
  let currentChatUser = null;

  // Connect to Socket.IO server
  const socket = io('http://localhost:3000'); // adjust as needed

  socket.on('connect', () => {
    socket.emit('join', { username });
  });

  socket.on('users', (serverUsers) => {
    // Filter out self from online users list (if you want to mark online separately)
    // Here we rely on our fetched full users list, so you can decide to merge or separate
    // For now, just ignore or do whatever you want here
  });

  socket.on('private_message', (msg) => {
    if (
      (msg.from === currentChatUser && msg.to === username) ||
      (msg.to === currentChatUser && msg.from === username)
    ) {
      addMessageToUI({
        text: msg.text,
        sent: msg.from === username,
      });
    } else {
      // Optionally show notification for message from other users
    }
  });

  // Fetch all users from backend PHP and render
  async function fetchUsers() {
    try {
      const res = await fetch('/fetch_users.php', { credentials: 'include' });
      if (!res.ok) throw new Error('Failed to fetch users');
      const data = await res.json();
      if (data.error) throw new Error(data.error);
      users = data.filter(u => u.username !== username); // exclude self
      renderUsers(users);
    } catch (err) {
      console.error('Error fetching users:', err);
    }
  }

  function renderUsers(usersToRender) {
    usersList.innerHTML = '';
    usersToRender.forEach(user => {
      const li = document.createElement('li');
      li.textContent = user.username;
      li.classList.add('user');
      li.tabIndex = 0;
      li.addEventListener('click', () => openChat(user.username));
      li.addEventListener('keypress', e => {
        if (e.key === 'Enter') openChat(user.username);
      });
      usersList.appendChild(li);
    });
  }

  userSearch.addEventListener('input', () => {
    const searchTerm = userSearch.value.toLowerCase();
    const filtered = users.filter(user =>
      user.username.toLowerCase().includes(searchTerm)
    );
    renderUsers(filtered);
  });

  async function openChat(user) {
    currentChatUser = user;
    chatWithTitle.textContent = `Chat with ${user}`;
    chatWindow.style.display = 'flex';
    usersListContainer.style.display = 'none';
    messageInput.value = '';
    messagesContainer.innerHTML = '';

    try {
      const res = await fetch(`fetch_messages.php?partner=${encodeURIComponent(user)}`, { credentials: 'include' });
      if (!res.ok) throw new Error('Failed to fetch chat history');
      const history = await res.json();
      history.forEach(msg => {
        addMessageToUI({ text: msg.message, sent: msg.sent });
      });
      scrollToBottom();
    } catch (err) {
      console.error('Error loading chat history:', err);
    }
  }

  backToUsersBtn.addEventListener('click', () => {
    chatWindow.style.display = 'none';
    usersListContainer.style.display = 'flex';
    currentChatUser = null;
  });

  sendBtn.addEventListener('click', sendMessage);
  messageInput.addEventListener('keypress', e => {
    if (e.key === 'Enter') sendMessage();
  });

  function sendMessage() {
    const text = messageInput.value.trim();
    if (!text || !currentChatUser) return;

    // Send via socket.io
    socket.emit('private_message', {
      to: currentChatUser,
      from: username,
      text,
    });

    addMessageToUI({ text, sent: true });
    messageInput.value = '';
    scrollToBottom();

    // Also send/save message via PHP API for persistence
    fetch('/send_message.php', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ receiver: currentChatUser, message: text }),
    })
    .then(res => res.json())
    .then(data => {
      if (!data.success) {
        console.error('Message failed:', data.error || 'Unknown error');
        // Optionally show UI error or retry
      }
    })
    .catch(err => console.error('Send message error:', err));
  }

  function addMessageToUI(message) {
    const div = document.createElement('div');
    div.classList.add('message');
    div.classList.add(message.sent ? 'sent' : 'received');
    div.textContent = message.text;
    messagesContainer.appendChild(div);
    scrollToBottom();
  }

  function scrollToBottom() {
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
  }

  // Initially fetch user list on page load
  fetchUsers();
});
