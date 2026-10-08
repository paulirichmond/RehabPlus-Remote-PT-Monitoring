</main>

</div>

<script>
	const themeToggle = document.getElementById('themeToggle');
	const applyTheme = (dark) => {
		document.documentElement.classList.toggle('dark-mode', dark);
		document.body.classList.toggle('dark-mode', dark);
		themeToggle.innerHTML = dark
			? '<i class="bi bi-sun-fill"></i>'
			: '<i class="bi bi-moon-fill"></i>';
	};

	if (themeToggle) {
		applyTheme(localStorage.getItem('rehabplus-theme') === 'dark');
		themeToggle.addEventListener('click', () => {
			const dark = !document.body.classList.contains('dark-mode');
			localStorage.setItem('rehabplus-theme', dark ? 'dark' : 'light');
			applyTheme(dark);
		});
	}
</script>

<!-- Bootstrap -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>