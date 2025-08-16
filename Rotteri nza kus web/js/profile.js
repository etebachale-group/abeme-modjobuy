document.addEventListener('DOMContentLoaded', function() {
    const orderItemHeaders = document.querySelectorAll('.order-item-header');
    
    orderItemHeaders.forEach(header => {
        header.addEventListener('click', function() {
            const body = this.nextElementSibling;
            const icon = this.querySelector('i');
            
            if (body.style.display === 'block') {
                body.style.display = 'none';
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
            } else {
                body.style.display = 'block';
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
            }
        });
    });
});