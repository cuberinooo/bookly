# Using Cross-Stack Enums

## PHP Backend (Symfony)

### Entity Usage
Use the generated PHP enum as a property type in your entities. Symfony will automatically handle the mapping if you use the `#[ORM\Column(type: 'string', enumType: CourseFrequency::class)]` attribute.

```php
use App\Enum\CourseFrequency;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Course
{
    #[ORM\Column(type: 'string', length: 255, enumType: CourseFrequency::class)]
    private CourseFrequency $frequency;

    public function getFrequency(): CourseFrequency
    {
        return $this->frequency;
    }

    public function setFrequency(CourseFrequency $frequency): void
    {
        $this->frequency = $frequency;
    }
}
```

### Controller/Service Usage
```php
use App\Enum\CourseFrequency;

$frequency = CourseFrequency::DAILY;
if ($course->getFrequency() === CourseFrequency::WEEKLY) {
    // ...
}
```

## TypeScript Frontend (Vue)

### Component Usage
Import and use the generated TS enum in your components or stores.

```typescript
import { CourseFrequency } from '@/app/enums/CourseFrequency';

const frequency = CourseFrequency.DAILY;

if (selectedFrequency === CourseFrequency.WEEKLY) {
    // ...
}
```

### Template Usage
```vue
<template>
  <select v-model="selectedFrequency">
    <option v-for="(value, key) in CourseFrequency" :key="key" :value="value">
      {{ key }}
    </option>
  </select>
</template>

<script setup lang="ts">
import { CourseFrequency } from '@/app/enums/CourseFrequency';
const selectedFrequency = ref(CourseFrequency.DAILY);
</script>
```
