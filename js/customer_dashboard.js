document.addEventListener("DOMContentLoaded", () => {
  console.log("customer_dashboard.js loaded ✅");

  const btn = document.getElementById("btnNewRequest");
  const modal = document.getElementById("requestModal");
  if (!btn || !modal) {
    console.error("Missing btnNewRequest or requestModal", { btn, modal });
    return;
  }

  const closeBtn = modal.querySelector(".close-modal");
  const cancelBtn = modal.querySelector(".btn-cancel");

  const openModal = () => {
    modal.classList.add("active");
    modal.style.display = "flex"; // force visible even if CSS differs
    modal.style.opacity = "1";
    modal.style.visibility = "visible";
  };

  const closeModal = () => {
    modal.classList.remove("active");
    modal.style.display = "none";
  };

  btn.addEventListener("click", (e) => {
    e.preventDefault();
    openModal();
  });

  if (closeBtn) closeBtn.addEventListener("click", closeModal);
  if (cancelBtn) cancelBtn.addEventListener("click", closeModal);

  modal.addEventListener("click", (e) => {
    if (e.target === modal) closeModal();
  });
});
