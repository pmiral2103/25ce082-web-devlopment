let namepattern=/^[A-Za-z]{2,50}$/;
const nameinput=document.getElementById('name');

let contactpattern=/^[6-9]{1}[0-9]{9}$/;
const contactinput=document.getElementById('contact');

const btn=document.getElementById('submit');


function valid()
{
    const name=nameinput.value.trim();
    if(name==="")
    {
        document.getElementById("nameerror").textContent="Name is required";
    }
    else if(!namepattern.test(name))
    {
        document.getElementById("nameerror").textContent="please enter a name in correct format";
    }
    else{
        document.getElementById("nameerror").textContent="";
    }


    const contact=contactinput.value.trim();
    if(contact==="")
    {
        document.getElementById("contacterror").textContent="contact is required";
    }
    else if(!contactpattern.test(contact))
    {
        document.getElementById("contacterror").textContent="please enter contact in correct format";
    }
    else{
        document.getElementById("contacterror").textContent="";
    }

   let gender = document.querySelector('input[name="gender"]:checked');

if (gender == null)
{
    document.getElementById("gendererror").textContent =
        "Please select a gender";
}
else
{
    document.getElementById("gendererror").textContent = "";
}
}


btn.addEventListener('click',function(){
    valid();
});
