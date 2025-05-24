// Switch visible page
function showPage(pageId) {
    document.querySelectorAll("section").forEach(section => section.classList.add("hidden"));
    document.getElementById(pageId).classList.remove("hidden");
  
    if (pageId === "enrollment") displayPendingEnrollees();
    if (pageId === "graduates") loadGraduates();
  }
  
  // Dummy data for pending enrollees
  let pendingEnrollees = [
    { name: "RANDOLF SUBA", course: "Welding", region: "REGION 6" },
    { name: "Maria Santos", course: "Housekeeping", region: "Region 6" },
    { name: "RANDOLF SUBA", course: "Welding", region: "REGION 6" },
    { name: "Maria Santos", course: "Housekeeping", region: "Region 6" },
    { name: "RANDOLF SUBA", course: "Welding", region: "REGION 6" },
    { name: "Maria Santos", course: "Housekeeping", region: "Region 6" },
    { name: "RANDOLF SUBA", course: "Welding", region: "REGION 6" },
    { name: "Maria Santos", course: "Housekeeping", region: "Region 6" },
    { name: "RANDOLF SUBA", course: "Welding", region: "REGION 6" },
    { name: "Maria Santos", course: "Housekeeping", region: "Region 6" },
    { name: "RANDOLF SUBA", course: "Welding", region: "REGION 6" },
    { name: "Maria Santos", course: "Housekeeping", region: "Region 6" },
    { name: "RANDOLF SUBA", course: "Welding", region: "REGION 6" },
    { name: "Maria Santos", course: "Housekeeping", region: "Region 6" },
  ];
  
  // Dummy data for graduates
  let dummyGrads = [
    { name: "Ana Reyes", course: "Cookery", year: "2022", status: "Employed" },
    { name: "Jose Vega", course: "Welding", year: "2021", status: "Unemployed" },
    { name: "Ana Reyes", course: "Cookery", year: "2022", status: "Employed" },
    { name: "Jose Vega", course: "Welding", year: "2021", status: "Unemployed" },
    { name: "Ana Reyes", course: "Cookery", year: "2022", status: "Employed" },
    { name: "Jose Vega", course: "Welding", year: "2021", status: "Unemployed" },
    { name: "Ana Reyes", course: "Cookery", year: "2022", status: "Employed" },
    { name: "Jose Vega", course: "Welding", year: "2021", status: "Unemployed" },
    { name: "Ana Reyes", course: "Cookery", year: "2022", status: "Employed" },
    { name: "Jose Vega", course: "Welding", year: "2021", status: "Unemployed" },
    { name: "Ana Reyes", course: "Cookery", year: "2022", status: "Employed" },
    { name: "Jose Vega", course: "Welding", year: "2021", status: "Unemployed" },
    { name: "Ana Reyes", course: "Cookery", year: "2022", status: "Employed" },
    { name: "Jose Vega", course: "Welding", year: "2021", status: "Unemployed" },
    { name: "Ana Reyes", course: "Cookery", year: "2022", status: "Employed" },
    { name: "Jose Vega", course: "Welding", year: "2021", status: "Unemployed" },
    { name: "Ana Reyes", course: "Cookery", year: "2022", status: "Employed" },
    { name: "Jose Vega", course: "Welding", year: "2021", status: "Unemployed" },
    { name: "Ana Reyes", course: "Cookery", year: "2022", status: "Employed" },
    { name: "Jose Vega", course: "Welding", year: "2021", status: "Unemployed" },
    { name: "Ana Reyes", course: "Cookery", year: "2022", status: "Employed" },
    { name: "Jose Vega", course: "Welding", year: "2021", status: "Unemployed" },
  ];
  
  // Dummy list for posted courses
  let postedCourses = [];
  
  // Display pending enrollees
  function displayPendingEnrollees() {
    const list = document.getElementById("pendingEnrollees");
    list.innerHTML = "";
    if (pendingEnrollees.length === 0) {
      list.innerHTML = `<p class="text-gray-500">No pending enrollment requests.</p>`;
      return;
    }
  
    pendingEnrollees.forEach((student, index) => {
      const item = document.createElement("li");
      item.className = "p-4 border rounded flex justify-between items-center bg-gray-50";
      item.innerHTML = `
        <div>
          <p><strong>Name:</strong> ${student.name}</p>
          <p><strong>Course:</strong> ${student.course}</p>
          <p><strong>Region:</strong> ${student.region}</p>
        </div>
        <div class="flex gap-2">
          <button onclick="approveEnrollee(${index})" class="bg-green-500 text-white px-3 py-1 rounded">Approve</button>
          <button onclick="rejectEnrollee(${index})" class="bg-red-500 text-white px-3 py-1 rounded">Reject</button>
        </div>
      `;
      list.appendChild(item);
    });
  }
  
  // Approve enrollee
  function approveEnrollee(index) {
    alert(`${pendingEnrollees[index].name} has been approved.`);
    pendingEnrollees.splice(index, 1);
    displayPendingEnrollees();
  }
  
  // Reject enrollee
  function rejectEnrollee(index) {
    alert(`${pendingEnrollees[index].name} has been rejected.`);
    pendingEnrollees.splice(index, 1);
    displayPendingEnrollees();
  }
  
  // Submit and display new course
  document.getElementById("courseForm").addEventListener("submit", function (e) {
    e.preventDefault();
    const name = document.getElementById("courseName").value.trim();
    const desc = document.getElementById("courseDescription").value.trim();
  
    if (!name || !desc) {
      alert("Please fill out all fields.");
      return;
    }
  
    postedCourses.push({ name, desc });
    renderCourses();
    this.reset();
  });
  
  // Render posted courses
  function renderCourses() {
    const list = document.getElementById("coursesContainer");
    list.innerHTML = "";
  
    if (postedCourses.length === 0) {
      list.innerHTML = `<p class="text-gray-500">No courses posted yet.</p>`;
      return;
    }
  
    postedCourses.forEach((course, i) => {
      const item = document.createElement("li");
      item.className = "mb-2";
      item.innerHTML = `<strong>${course.name}</strong>: ${course.desc}`;
      list.appendChild(item);
    });
  }
  
  // Load graduate data into the table
  function loadGraduates() {
    const tbody = document.getElementById("graduateList");
    tbody.innerHTML = "";
  
    if (dummyGrads.length === 0) {
      tbody.innerHTML = `<tr><td colspan="5" class="text-center text-gray-500 p-4">No graduate records available.</td></tr>`;
      return;
    }
  
    dummyGrads.forEach((grad, i) => {
      const row = document.createElement("tr");
      row.className = "hover:bg-gray-100";
      row.innerHTML = `
        <td class="border p-2">${grad.name}</td>
        <td class="border p-2">${grad.course}</td>
        <td class="border p-2">${grad.year}</td>
        <td class="border p-2">${grad.status}</td>
        <td class="border p-2 text-center">
          <button onclick="removeGrad(${i})" class="text-red-600 hover:underline">Delete</button>
        </td>
      `;
      tbody.appendChild(row);
    });
  }
  
  // Delete graduate entry
  function removeGrad(index) {
    if (confirm(`Are you sure you want to delete ${dummyGrads[index].name}'s record?`)) {
      dummyGrads.splice(index, 1);
      loadGraduates();
    }
  }
  
  // Search graduates
  function searchGraduates() {
    const term = document.getElementById("searchGraduate").value.toLowerCase();
    const rows = document.querySelectorAll("#graduateList tr");
  
    rows.forEach(row => {
      const content = row.innerText.toLowerCase();
      row.style.display = content.includes(term) ? "" : "none";
    });
  }
  
  // Initialize first page
  document.addEventListener("DOMContentLoaded", () => {
    showPage("home");
    renderCourses();
  });
  