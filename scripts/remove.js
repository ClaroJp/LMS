function removeStudent(studentId) {
    if (!confirm("Are you sure you want to remove this student?")) return;

    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'ajax_remove_student_id=' + encodeURIComponent(studentId)
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            // Remove the student from the DOM
            const li = document.getElementById('student-' + studentId);
            if (li) li.remove();
            alert('Student removed successfully.');
        } else {
            alert('Error removing student: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(() => alert('Request failed. Please try again.'));
}
