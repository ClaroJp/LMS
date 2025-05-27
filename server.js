const { createServer } = require('http');
const { Server } = require('socket.io');

// Create HTTP server (optional, you can also use your existing one)
const httpServer = createServer();

const io = new Server(httpServer, {
  cors: {
    origin: '*',   // For dev, allow all origins. Change in production!
    methods: ['GET', 'POST']
  }
});

const onlineUsers = new Map(); // socket.id => username

io.on('connection', (socket) => {
  console.log('New client connected:', socket.id);

  // Handle join with username
  socket.on('join', ({ username }) => {
    if (!username) return;

    onlineUsers.set(socket.id, username);
    console.log(`${username} joined with socket id ${socket.id}`);

    // Emit updated user list to everyone as an array
    emitUsers();
  });

  // Handle private messages
  socket.on('private_message', (msg) => {
    // msg = { from, to, text }
    console.log(`Private message from ${msg.from} to ${msg.to}: ${msg.text}`);

    // Find socket id of the recipient
    for (const [id, username] of onlineUsers.entries()) {
      if (username === msg.to) {
        io.to(id).emit('private_message', msg);
        break;
      }
    }

  });

  // Handle disconnect
  socket.on('disconnect', () => {
    const username = onlineUsers.get(socket.id);
    onlineUsers.delete(socket.id);
    console.log(`${username || 'Unknown user'} disconnected`);

    // Emit updated user list
    emitUsers();
  });

  // Emit users helper function
  function emitUsers() {
    // Get unique usernames (no duplicates)
    const users = [...new Set(onlineUsers.values())];
    io.emit('users', users);  // Emit array directly
  }
});

const PORT = 3000;
httpServer.listen(PORT, () => {
  console.log(`Socket.IO server running on port ${PORT}`);
});
