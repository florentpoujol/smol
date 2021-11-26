
no escape: {{ simpleVar }}
short escape: {{ simpleVar|e }}
long escape: {{ simpleVar|escape }}

for: {% for value in array %}
inside for array: {{ value }}
endfor: {% endfor %}

for: {% for myModel in arrayOfObject %}
inside for object: {{ myModel.property }}
endfor: {% endfor %}
