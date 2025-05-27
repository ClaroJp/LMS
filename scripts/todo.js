document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('task-form');
    const input = document.getElementById('task-text');
    const list = document.getElementById('task-list');

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        const task = input.value.trim();
        if (task === '') return;

        const res = await fetch('add_task.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({ task })
        });

        if (await res.text() === 'success') {
            location.reload();
        }
    });

    list.addEventListener('click', async function (e) {
        if (e.target.classList.contains('delete-btn')) {
            const li = e.target.closest('li');
            const id = li.dataset.id;

            const res = await fetch('delete_task.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({ task_id: id })
            });

            if (await res.text() === 'deleted') {
                li.remove();
            }
        }
    });
});
