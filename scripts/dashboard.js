const menuBtn = document.getElementById('menuBtn');
const sidebar = document.querySelector('.sidebar');

menuBtn.addEventListener('click', () => {
    sidebar.classList.toggle('active');
    // Hide the menu button when sidebar is visible
    if (sidebar.classList.contains('active')) {
        menuBtn.style.display = 'none';
    }
});

// Optional: Clicking outside sidebar closes it and shows the button again
document.addEventListener('click', (e) => {
    if (
        !sidebar.contains(e.target) &&
        e.target !== menuBtn &&
        sidebar.classList.contains('active')
    ) {
        sidebar.classList.remove('active');
        menuBtn.style.display = 'block';
    }
});
